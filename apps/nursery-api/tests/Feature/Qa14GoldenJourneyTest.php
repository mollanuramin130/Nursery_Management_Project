<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-14 — Golden business journey (API E2E): cart → COD → pick/pack/ship/deliver → customer status.
 */
class Qa14GoldenJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(): User
    {
        $user = User::query()->create([
            'name' => 'QA14 Cust',
            'email' => 'qa14-cust@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function admin(): User
    {
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user = User::query()->create([
            'name' => 'QA14 Admin',
            'email' => 'qa14-admin@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function product(int $qty = 10): Product
    {
        $product = Product::query()->create([
            'name' => 'QA14 Plant',
            'slug' => 'qa14-plant-'.uniqid(),
            'sku' => 'QA14-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA14'],
            ['name' => 'QA14 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    public function test_golden_cod_fulfillment_to_delivered_visible_to_customer(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $product = $this->product(8);
        $cToken = JWTAuth::fromUser($customer);
        $aToken = JWTAuth::fromUser($admin);

        $this->withToken($cToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA14',
            'phone' => '9999999999',
            'line1' => '1 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA14',
            'name' => 'Standard',
            'price' => 49,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $preview = $this->withToken($cToken)
            ->postJson('/api/v1/checkout/preview', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
            ])
            ->assertOk()
            ->json('data');
        $this->assertNotNull($preview['grand_total'] ?? null);

        $order = $this->withToken($cToken)
            ->withHeader('X-Request-Id', 'qa14_golden_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])->assertSuccessful()->json('data');

        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertGreaterThan(0, $orderId);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);

        // Idempotent replay
        $again = $this->withToken($cToken)
            ->withHeader('X-Request-Id', 'qa14_golden_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])->assertSuccessful()->json('data');
        $againId = (int) ($again['id'] ?? $again['order']['id'] ?? 0);
        $this->assertSame($orderId, $againId);

        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->actingAs($admin->fresh(['roles.permissions']), 'api')
            ->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/start")
            ->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/scan", [
            'code' => $product->sku,
            'increment_by' => 1,
        ])->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/complete")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pack", [
            'package_count' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'PACKED');
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/ship", [
            'carrier' => 'manual',
            'tracking_number' => 'QA14-AUTO-1',
        ])->assertSuccessful();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/out-for-delivery")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/deliver", [
            'method' => 'note',
            'note' => 'QA14 delivered',
        ])->assertOk()->assertJsonPath('data.status', 'DELIVERED');

        auth()->forgetGuards();
        $this->flushHeaders();
        $this->actingAs($customer, 'api')
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERED');
    }

    public function test_customer_cannot_hit_admin_and_wishlist_conflict_is_safe(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertSuccessful();
        $this->withToken($token)
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertStatus(409);
    }
}
