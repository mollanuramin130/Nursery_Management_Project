<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\CustomerProfile;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Notification\Models\Notification;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase4CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomer(): User
    {
        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer'],
        );

        $user = User::query()->create([
            'name' => 'Phase Four Customer',
            'email' => 'phase4@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        CustomerProfile::query()->create([
            'user_id' => $user->id,
            'marketing_opt_in' => false,
            'preferred_language' => 'en',
            'meta' => [],
        ]);

        return $user->fresh(['roles', 'customerProfile']);
    }

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_preferences_get_and_update(): void
    {
        $user = $this->seedCustomer();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/customer/preferences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.marketing_opt_in', false)
            ->assertJsonPath('data.notify_orders', true);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/customer/preferences', [
                'marketing_opt_in' => true,
                'notify_promotions' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.marketing_opt_in', true)
            ->assertJsonPath('data.notify_promotions', false);
    }

    public function test_notifications_are_scoped_to_owner(): void
    {
        $user = $this->seedCustomer();
        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'other-phase4@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $other->forceFill(['status' => 'active'])->save();

        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'ORDER_CONFIRMED',
            'title' => 'Yours',
            'body' => 'Hello',
            'data_json' => ['order_id' => 1],
            'is_read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $other->id,
            'type' => 'ORDER_CONFIRMED',
            'title' => 'Not yours',
            'body' => 'Secret',
            'data_json' => [],
            'is_read' => false,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Yours');
    }

    public function test_review_eligibility_requires_purchase(): void
    {
        $user = $this->seedCustomer();
        $product = Product::query()->create([
            'name' => 'Test Plant',
            'slug' => 'test-plant-phase4',
            'sku' => 'P4-001',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'rating_avg' => 0,
            'rating_count' => 0,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/products/'.$product->id.'/review-eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false);

        $order = Order::query()->create([
            'order_number' => 'GL-P4-1',
            'user_id' => $user->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 199,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 199,
            'payment_method' => 'cod',
            'shipping_address_json' => [
                'name' => 'Test',
                'phone' => '9999999999',
                'line1' => '1 Lane',
                'city' => 'Pune',
                'state' => 'MH',
                'postal_code' => '411001',
                'country' => 'IN',
            ],
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 199,
            'quantity' => 1,
            'line_total' => 199,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/products/'.$product->id.'/review-eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/products/'.$product->id.'/reviews', [
                'rating' => 5,
                'title' => 'Great',
                'body' => 'Healthy plant',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.verified_purchase', true);
    }

    public function test_order_detail_exposes_can_return_when_delivered(): void
    {
        $user = $this->seedCustomer();
        $product = Product::query()->create([
            'name' => 'Return Plant',
            'slug' => 'return-plant-phase4',
            'sku' => 'P4-002',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 100,
            'currency' => 'INR',
            'rating_avg' => 0,
            'rating_count' => 0,
        ]);
        $order = Order::query()->create([
            'order_number' => 'GL-P4-2',
            'user_id' => $user->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'shipping_address_json' => [
                'name' => 'Test',
                'phone' => '9999999999',
                'line1' => '1 Lane',
                'city' => 'Pune',
                'state' => 'MH',
                'postal_code' => '411001',
                'country' => 'IN',
            ],
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => 'Plant',
            'sku' => 'X',
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.can_return', true)
            ->assertJsonPath('data.actions.can_return', true);
    }
}
