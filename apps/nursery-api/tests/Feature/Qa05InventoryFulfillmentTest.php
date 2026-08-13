<?php

namespace Tests\Feature;

use App\Console\Commands\ReleaseExpiredReservationsCommand;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\CheckoutService;
use App\Modules\Order\Services\OrderStateMachine;
use App\Shared\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-05 — Inventory reservation/commit/release + fulfillment + delivery regression.
 */
class Qa05InventoryFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'qa05-cust@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'QA05 Customer',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function staff(array $perms, string $email = 'qa05-ops@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'QA05 Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    /**
     * @return array{warehouse: Warehouse, product: Product, item: InventoryItem, shipping: ShippingMethod}
     */
    private function seedCatalog(int $onHand = 5, string $sku = 'QA05-SKU'): array
    {
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'QA05-WH'],
            ['name' => 'QA05 Warehouse', 'is_default' => true, 'status' => 'active'],
        );
        if (! $warehouse->is_default) {
            $warehouse->forceFill(['is_default' => true])->save();
        }

        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'QA05 Plant',
            'slug' => 'qa05-'.uniqid(),
            'sku' => $sku.'-'.uniqid(),
            'price' => 100,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);
        $item = InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => $onHand,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $shipping = ShippingMethod::query()->firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard',
                'price' => 49,
                'currency' => 'INR',
                'eta_min_days' => 2,
                'eta_max_days' => 5,
                'status' => 'active',
            ],
        );

        return compact('warehouse', 'product', 'item', 'shipping');
    }

    public function test_last_unit_cannot_be_double_reserved(): void
    {
        $seed = $this->seedCatalog(1);
        $svc = app(InventoryService::class);
        $ok = 0;
        $fail = 0;

        try {
            $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 1]], 'order', 9001);
            $ok++;
        } catch (ApiException) {
            $fail++;
        }

        try {
            $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 1]], 'order', 9002);
            $ok++;
        } catch (ApiException) {
            $fail++;
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $seed['item']->refresh();
        $this->assertSame(1, (int) $seed['item']->qty_reserved);
        $this->assertSame(0, $svc->sellable($seed['item']));
        $this->assertGreaterThanOrEqual(0, (int) $seed['item']->qty_on_hand);
    }

    public function test_duplicate_reserve_same_order_is_idempotent(): void
    {
        $seed = $this->seedCatalog(5);
        $svc = app(InventoryService::class);
        $lines = [['product_id' => $seed['product']->id, 'quantity' => 2]];

        $svc->reserve($lines, 'order', 55);
        $svc->reserve($lines, 'order', 55);

        $seed['item']->refresh();
        $this->assertSame(2, (int) $seed['item']->qty_reserved);
        $this->assertSame(1, StockMovement::query()->where('type', 'reserve')->where('reference_id', 55)->count());
    }

    public function test_cod_commits_stock_and_cancel_restocks(): void
    {
        $user = $this->customer();
        $token = JWTAuth::fromUser($user);
        $seed = $this->seedCatalog(10);
        $product = $seed['product'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $user->id,
            'name' => 'QA05',
            'phone' => '9000000001',
            'line1' => 'Street',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);

        $order = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $seed['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('CONFIRMED', $order['status']);
        $seed['item']->refresh();
        $this->assertSame(8, (int) $seed['item']->qty_on_hand);
        $this->assertSame(0, (int) $seed['item']->qty_reserved);
        $this->assertSame(1, StockMovement::query()->where('type', 'sale')->where('reference_id', $order['id'])->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
                'reason_code' => 'changed_mind',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $seed['item']->refresh();
        $this->assertSame(10, (int) $seed['item']->qty_on_hand);
        $this->assertSame(0, (int) $seed['item']->qty_reserved);
    }

    public function test_expired_reservation_uses_order_reference_so_cancel_cannot_over_release(): void
    {
        $seed = $this->seedCatalog(5);
        $svc = app(InventoryService::class);
        $productId = $seed['product']->id;

        // Order A: reserved 1 (will expire)
        $svc->reserve([['product_id' => $productId, 'quantity' => 1]], 'order', 501);
        // Order B: reserved 2 (must remain intact)
        $svc->reserve([['product_id' => $productId, 'quantity' => 2]], 'order', 502);

        $user = $this->customer('qa05-expire@example.com');
        $orderA = Order::query()->create([
            'order_number' => 'QA05-EXP-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'razorpay',
            'warehouse_id' => $seed['warehouse']->id,
            'shipping_address_json' => ['name' => 'A'],
            'billing_address_json' => ['name' => 'A'],
            'placed_at' => now()->subHours(48),
            'created_at' => now()->subHours(48),
            'updated_at' => now()->subHours(48),
        ]);
        // Force created_at past cutoff (Eloquent may overwrite timestamps).
        Order::query()->whereKey($orderA->id)->update([
            'created_at' => now()->subHours(48),
            'updated_at' => now()->subHours(48),
        ]);
        OrderItem::query()->create([
            'order_id' => $orderA->id,
            'product_id' => $productId,
            'sku' => $seed['product']->sku,
            'name' => $seed['product']->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        // Remap reserve reference to real order id (simulates placeOrder).
        StockMovement::query()
            ->where('type', 'reserve')
            ->where('reference_type', 'order')
            ->where('reference_id', 501)
            ->update(['reference_id' => $orderA->id]);

        $seed['item']->refresh();
        $this->assertSame(3, (int) $seed['item']->qty_reserved);

        Artisan::call(ReleaseExpiredReservationsCommand::class, ['--hours' => 24]);

        $orderA->refresh();
        $this->assertSame('PAYMENT_FAILED', $orderA->status);
        $seed['item']->refresh();
        $this->assertSame(2, (int) $seed['item']->qty_reserved, 'Only order A reservation released');

        // Customer cancel after expiry must NOT steal order B reservation.
        app(CheckoutService::class)->cancel($user, $orderA->id, null, 'changed_mind');

        $seed['item']->refresh();
        $this->assertSame(2, (int) $seed['item']->qty_reserved);
        $this->assertSame(0, StockMovement::query()
            ->where('type', 'release')
            ->where('reference_type', 'order_expired')
            ->count());
        $this->assertSame(1, StockMovement::query()
            ->where('type', 'release')
            ->where('reference_type', 'order')
            ->where('reference_id', $orderA->id)
            ->count());
    }

    public function test_fulfillment_happy_path_and_customer_tracking(): void
    {
        $admin = $this->staff([
            'fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship',
        ]);
        $customer = $this->customer('qa05-track@example.com');
        $seed = $this->seedCatalog(20, 'QA05-FUL');

        $order = Order::query()->create([
            'order_number' => 'QA05-FUL-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 200,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 200,
            'payment_method' => 'cod',
            'warehouse_id' => $seed['warehouse']->id,
            'shipping_address_json' => [
                'name' => 'C', 'phone' => '9', 'line1' => 'L', 'city' => 'Pune',
                'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
            ],
            'billing_address_json' => [
                'name' => 'C', 'phone' => '9', 'line1' => 'L', 'city' => 'Pune',
                'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
            ],
            'confirmed_at' => now(),
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $seed['product']->id,
            'sku' => $seed['product']->sku,
            'name' => $seed['product']->name,
            'unit_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
        ]);

        $id = $order->id;
        $skuCode = $seed['product']->sku;
        $adminToken = JWTAuth::fromUser($admin);

        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")->assertOk();
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/scan", [
            'code' => $skuCode,
            'increment_by' => 2,
        ])->assertOk();
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/complete")->assertOk();
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pack", [
            'package_count' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'PACKED');
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", [])
            ->assertCreated();
        $this->assertSame('SHIPPED', Order::query()->find($id)?->status);
        $this->assertSame('shipped', Order::query()->with('shipment')->find($id)?->shipment?->status);
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/out-for-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/fail-delivery", [
            'reason' => 'CUSTOMER_UNAVAILABLE',
            'note' => 'No answer',
        ])->assertOk()->assertJsonPath('data.status', 'DELIVERY_FAILED');
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/retry-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');
        $this->withToken($adminToken)->postJson("/api/v1/admin/fulfillment/orders/{$id}/deliver", [
            'method' => 'note',
            'note' => 'Handed to customer',
        ])->assertOk()->assertJsonPath('data.status', 'DELIVERED');

        // Customer sees authoritative final status (clear sticky admin auth first).
        auth()->logout();
        auth()->forgetGuards();
        $custToken = JWTAuth::fromUser($customer->fresh());
        $final = $this->withToken($custToken)
            ->getJson("/api/v1/orders/{$id}")
            ->assertOk()
            ->json('data');
        $this->assertSame('DELIVERED', $final['status']);

        $tracking = $this->withToken($custToken)
            ->getJson("/api/v1/orders/{$id}/tracking")
            ->assertOk()
            ->json('data');
        $steps = $tracking['timeline'] ?? $tracking['steps'] ?? [];
        $statuses = collect($steps)->pluck('status')->all();
        $this->assertContains('DELIVERED', $statuses);
        $this->assertTrue(collect($steps)->contains(fn ($s) => ($s['status'] ?? null) === 'DELIVERED' && ($s['current'] ?? false) === true));
    }

    public function test_invalid_order_transition_rejected(): void
    {
        $seed = $this->seedCatalog(3);
        $user = $this->customer('qa05-sm@example.com');
        $order = Order::query()->create([
            'order_number' => 'QA05-SM-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'warehouse_id' => $seed['warehouse']->id,
            'shipping_address_json' => ['name' => 'X'],
            'billing_address_json' => ['name' => 'X'],
            'placed_at' => now(),
            'confirmed_at' => now(),
        ]);

        $this->expectException(ApiException::class);
        app(OrderStateMachine::class)->transition($order, 'DELIVERED', null, 'illegal');
    }

    public function test_view_only_staff_cannot_start_picking(): void
    {
        $viewer = $this->staff(['fulfillment.view'], 'qa05-view@example.com');
        $token = JWTAuth::fromUser($viewer);
        $customer = $this->customer('qa05-vorder@example.com');
        $seed = $this->seedCatalog(5, 'QA05-PERM');

        $order = Order::query()->create([
            'order_number' => 'QA05-PERM-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'warehouse_id' => $seed['warehouse']->id,
            'shipping_address_json' => ['name' => 'X'],
            'billing_address_json' => ['name' => 'X'],
            'placed_at' => now(),
            'confirmed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $seed['product']->id,
            'sku' => $seed['product']->sku,
            'name' => $seed['product']->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/fulfillment/orders/'.$order->id.'/pick/start')
            ->assertStatus(403);
    }
}
