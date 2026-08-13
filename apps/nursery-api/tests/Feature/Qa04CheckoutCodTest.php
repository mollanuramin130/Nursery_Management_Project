<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-04 — COD place order + checkout preview required for totals integrity.
 */
class Qa04CheckoutCodTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomer(): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'QA04 COD',
            'email' => 'qa04-cod@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function makeProduct(float $price = 199): Product
    {
        $product = Product::query()->create([
            'name' => 'QA04 Plant',
            'slug' => 'qa04-plant-'.uniqid(),
            'sku' => 'QA04-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => $price,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA04'],
            ['name' => 'QA04 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 20,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    public function test_cod_place_order_confirms_and_returns_order(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Request-Id' => 'qa04_cod_1'];
        $product = $this->makeProduct(249);

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA',
            'phone' => '9999999999',
            'line1' => '1 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);

        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA04',
            'name' => 'Standard',
            'price' => 49,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $preview = $this->withHeaders($headers)
            ->postJson('/api/v1/checkout/preview', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
            ])
            ->assertOk()
            ->json('data');

        $this->assertEquals(249.0, (float) $preview['subtotal']);
        $this->assertEquals(49.0, (float) $preview['shipping_total']);
        $this->assertEquals(298.0, (float) $preview['grand_total']);

        $order = $this->withHeaders($headers)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('CONFIRMED', $order['status']);
        $this->assertEquals(298.0, (float) $order['grand_total']);
        $this->assertEquals(
            (float) $preview['grand_total'],
            (float) $order['grand_total'],
        );
    }

    public function test_duplicate_request_id_does_not_create_second_order(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $rid = 'qa04_idem_1';
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Request-Id' => $rid];
        $product = $this->makeProduct(199);

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA',
            'phone' => '9999999999',
            'line1' => '1 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);

        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA04B',
            'name' => 'Standard',
            'price' => 49,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        // Same X-Request-Id must not create a second order (controller still returns 201 envelope).
        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['order_number'], $second['order_number']);
    }
}
