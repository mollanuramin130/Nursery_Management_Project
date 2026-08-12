<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\Shipment;
use App\Modules\Payment\Models\Payment;
use App\Modules\Review\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase9EngagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms, string $email = 'engage-ops@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Engage Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        // Use super_admin so permission middleware mirrors production bypass for ops tests.
        // Still attach explicit permission rows for role/permission table integrity.
        $role = Role::query()->where('slug', 'super_admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')->all()
        );

        return $user->fresh(['roles.permissions']);
    }

    private function customer(string $email = 'buyer-p9@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'Buyer Nine',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh(['roles']);
    }

    /**
     * @return array{order: Order, item: OrderItem, product: Product, customer: User}
     */
    private function seedDeliveredOrder(int $qty = 2, ?User $customer = null): array
    {
        $customer ??= $this->customer();
        $warehouse = Warehouse::query()->create([
            'code' => 'P9',
            'name' => 'P9 WH',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Peace Lily P9',
            'slug' => 'peace-p9-'.uniqid(),
            'sku' => 'PL-P9-'.uniqid(),
            'price' => 200,
            'currency' => 'INR',
            'status' => 'active',
        ]);
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 20,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        $addr = [
            'name' => 'Buyer', 'phone' => '9999999999', 'line1' => 'Street 1',
            'city' => 'Pune', 'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
        ];
        $order = Order::query()->create([
            'order_number' => 'GL-P9-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 200 * $qty,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 200 * $qty,
            'payment_method' => 'online',
            'warehouse_id' => $warehouse->id,
            'shipping_address_json' => $addr,
            'billing_address_json' => $addr,
            'confirmed_at' => now()->subDays(3),
            'placed_at' => now()->subDays(3),
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 200,
            'quantity' => $qty,
            'line_total' => 200 * $qty,
        ]);
        Shipment::query()->create([
            'order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'DELIVERED',
            'carrier' => 'internal',
            'tracking_number' => 'TRK-P9-'.uniqid(),
            'delivered_at' => now()->subDay(),
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'razorpay',
            'method' => 'online',
            'amount' => 200 * $qty,
            'currency' => 'INR',
            'status' => 'success',
            'idempotency_key' => 'pay-p9-'.uniqid(),
            'paid_at' => now()->subDays(3),
        ]);

        return compact('order', 'item', 'product', 'customer');
    }

    private function authHeader(User $user): array
    {
        $this->flushHeaders();
        auth()->forgetGuards();
        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
            // ignore
        }

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user->fresh())];
    }

    public function test_verified_purchase_review_and_duplicate_rejection(): void
    {
        $seed = $this->seedDeliveredOrder();
        $headers = $this->authHeader($seed['customer']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/products/'.$seed['product']->id.'/review-eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true);

        $this->withHeaders($headers)
            ->postJson('/api/v1/products/'.$seed['product']->id.'/reviews', [
                'rating' => 5,
                'title' => 'Great plant',
                'body' => 'Arrived healthy',
                'order_id' => $seed['order']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->withHeaders($headers)
            ->postJson('/api/v1/products/'.$seed['product']->id.'/reviews', [
                'rating' => 4,
                'body' => 'Again',
            ])
            ->assertStatus(409);
    }

    public function test_non_purchased_and_invalid_rating_rejected(): void
    {
        $seed = $this->seedDeliveredOrder();
        $other = $this->customer('other-p9@example.com');
        $headers = $this->authHeader($other);

        $this->withHeaders($headers)
            ->postJson('/api/v1/products/'.$seed['product']->id.'/reviews', [
                'rating' => 5,
                'body' => 'Nope',
            ])
            ->assertStatus(403);

        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/products/'.$seed['product']->id.'/reviews', [
                'rating' => 9,
                'body' => 'Bad',
            ])
            ->assertStatus(422);
    }

    public function test_admin_review_queue_and_moderation(): void
    {
        $seed = $this->seedDeliveredOrder();
        $review = Review::query()->create([
            'product_id' => $seed['product']->id,
            'user_id' => $seed['customer']->id,
            'order_id' => $seed['order']->id,
            'rating' => 4,
            'title' => 'Nice',
            'body' => 'Nice plant',
            'status' => 'pending',
        ]);

        $admin = $this->staff(['reviews.view', 'reviews.moderate']);
        $headers = $this->authHeader($admin);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/reviews?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/reviews/'.$review->id.'/moderate', ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(1, (int) $seed['product']->fresh()->rating_count);
        $this->assertEqualsWithDelta(4.0, (float) $seed['product']->fresh()->rating_avg, 0.01);
    }

    public function test_loyalty_earn_idempotent_and_refund_reverse(): void
    {
        $seed = $this->seedDeliveredOrder(1);
        /** @var LoyaltyService $loyalty */
        $loyalty = app(LoyaltyService::class);

        $first = $loyalty->earnForDeliveredOrder($seed['order']);
        $second = $loyalty->earnForDeliveredOrder($seed['order']);

        $this->assertNotNull($first);
        $this->assertFalse($first['idempotent_replay'] ?? true);
        $this->assertTrue($second['idempotent_replay'] ?? false);
        $this->assertSame(1, LoyaltyTransaction::query()->where('type', 'EARN')->count());

        $admin = $this->staff(['payments.refund', 'loyalty.view'], 'refund-p9@example.com');
        $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/v1/admin/refunds', [
                'order_id' => $seed['order']->id,
                'amount' => 200,
                'reason' => 'Full refund test',
                'idempotency_key' => 'p9-refund-1',
            ])
            ->assertCreated();

        $this->assertSame(1, LoyaltyTransaction::query()->where('type', 'REVERSE')->count());

        $balance = $loyalty->forUser($seed['customer'])['balance'];
        $this->assertSame(0, $balance);
    }

    public function test_manual_loyalty_adjust_requires_reason_and_permission(): void
    {
        $customer = $this->customer('adj-p9@example.com');
        /** @var LoyaltyService $loyalty */
        $loyalty = app(LoyaltyService::class);
        $result = $loyalty->adjust($customer->id, 50, 'CS goodwill', null, 'adj-1');
        $this->assertSame(50, $result['points']);

        $viewer = User::query()->create([
            'name' => 'View Only',
            'email' => 'view-only@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $viewer->forceFill(['status' => 'active'])->save();
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Permission::query()->firstOrCreate(['slug' => 'loyalty.view'], ['name' => 'loyalty.view']);
        $viewer->roles()->sync([$adminRole->id]);
        $adminRole->permissions()->sync(
            Permission::query()->where('slug', 'loyalty.view')->pluck('id')->all()
        );

        $this->withHeaders($this->authHeader($viewer->fresh(['roles.permissions'])))
            ->postJson('/api/v1/admin/loyalty/adjust', [
                'user_id' => $customer->id,
                'points' => 10,
                'reason' => 'Nope',
            ])
            ->assertStatus(403);
    }

    public function test_customer_loyalty_and_reviews_isolation(): void
    {
        $seed = $this->seedDeliveredOrder();
        $other = $this->customer('iso-p9@example.com');

        Review::query()->create([
            'product_id' => $seed['product']->id,
            'user_id' => $seed['customer']->id,
            'order_id' => $seed['order']->id,
            'rating' => 5,
            'body' => 'Mine',
            'status' => 'pending',
        ]);

        /** @var \App\Modules\Review\Services\ReviewService $reviews */
        $reviews = app(\App\Modules\Review\Services\ReviewService::class);
        $mine = $reviews->listForUser($seed['customer']);
        $theirs = $reviews->listForUser($other);

        $this->assertCount(1, $mine['data']);
        $this->assertCount(0, $theirs['data']);

        $loyalty = app(LoyaltyService::class)->forUser($seed['customer']);
        $this->assertSame(0, $loyalty['balance']);
    }
}
