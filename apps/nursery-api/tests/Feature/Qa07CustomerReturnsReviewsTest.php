<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\ReturnItem;
use App\Modules\Order\Models\ReturnRequest;
use App\Modules\Review\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-07 — Customer returns list + my reviews API (Mobile parity contracts).
 */
class Qa07CustomerReturnsReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'qa07@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'QA07',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    public function test_customer_returns_list_and_show(): void
    {
        $user = $this->customer();
        $token = JWTAuth::fromUser($user);

        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'QA07 Return Plant',
            'slug' => 'qa07-ret-'.uniqid(),
            'sku' => 'QA07-RET-'.uniqid(),
            'price' => 100,
            'currency' => 'INR',
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'order_number' => 'QA07-R-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'shipping_address_json' => ['name' => 'A'],
            'billing_address_json' => ['name' => 'A'],
            'placed_at' => now()->subDays(10),
            'confirmed_at' => now()->subDays(9),
        ]);
        $oi = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);
        $ret = ReturnRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => 'RETURN_REQUESTED',
            'notes' => 'Test',
        ]);
        ReturnItem::query()->create([
            'return_request_id' => $ret->id,
            'order_item_id' => $oi->id,
            'quantity' => 1,
            'reason' => 'damaged',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/customer/returns')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $ret->id)
            ->assertJsonPath('data.0.order_id', $order->id);

        $this->withToken($token)
            ->getJson('/api/v1/returns/'.$ret->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'RETURN_REQUESTED')
            ->assertJsonPath('data.items.0.reason', 'damaged');
    }

    public function test_customer_my_reviews_list(): void
    {
        $user = $this->customer('qa07-rev@example.com');
        $token = JWTAuth::fromUser($user);

        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'QA07 Review Plant',
            'slug' => 'qa07-rev-'.uniqid(),
            'sku' => 'QA07-REV-'.uniqid(),
            'price' => 199,
            'currency' => 'INR',
            'status' => 'active',
        ]);

        Review::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 4,
            'title' => 'Nice',
            'body' => 'Good plant',
            'status' => 'approved',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/customer/reviews')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_guest_cannot_list_returns_or_reviews(): void
    {
        $this->getJson('/api/v1/customer/returns')->assertStatus(401);
        $this->getJson('/api/v1/customer/reviews')->assertStatus(401);
    }
}
