<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Customer\Models\Address;
use App\Modules\Promotion\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Qa03CartTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomer(string $email = 'qa-cart@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);

        $user = User::query()->create([
            'name' => 'QA Cart',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function makeSellableProduct(float $price = 1000, int $qty = 50): Product
    {
        $product = Product::query()->create([
            'name' => 'QA Plant '.$price,
            'slug' => 'qa-plant-'.str_replace('.', '-', (string) $price).'-'.uniqid(),
            'sku' => 'QA-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => $price,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);

        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA03'],
            ['name' => 'QA03 WH', 'status' => 'active'],
        );

        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    public function test_free_delivery_meta_uses_merchandise_argument(): void
    {
        putenv('FREE_DELIVERY_THRESHOLD=999');
        $_ENV['FREE_DELIVERY_THRESHOLD'] = '999';

        $svc = app(CartService::class);
        $below = $svc->freeDeliveryMeta(900.0);
        $this->assertFalse($below['qualifies']);
        $this->assertEquals(99.0, $below['remaining']);

        $exact = $svc->freeDeliveryMeta(999.0);
        $this->assertTrue($exact['qualifies']);
        $this->assertEquals(0.0, $exact['remaining']);

        $above = $svc->freeDeliveryMeta(1100.0);
        $this->assertTrue($above['qualifies']);
    }

    public function test_cart_and_checkout_free_delivery_agree_with_coupon(): void
    {
        putenv('FREE_DELIVERY_THRESHOLD=999');
        $_ENV['FREE_DELIVERY_THRESHOLD'] = '999';

        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(1000);

        Coupon::query()->create([
            'code' => 'FLAT100',
            'name' => 'Flat 100',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_public' => true,
        ]);

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.free_delivery.qualifies', true);

        $cart = $this->withHeaders($headers)
            ->postJson('/api/v1/cart/apply-coupon', ['code' => 'FLAT100'])
            ->assertOk()
            ->json('data');

        $this->assertEquals(1000.0, (float) $cart['subtotal']);
        $this->assertEquals(100.0, (float) $cart['discount_total']);
        $this->assertFalse($cart['free_delivery']['qualifies']);
        $this->assertEquals(99.0, (float) $cart['free_delivery']['remaining']);

        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA',
            'phone' => '9999999999',
            'line1' => '1 Test St',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);

        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA03',
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
                'coupon_code' => 'FLAT100',
            ])
            ->assertOk()
            ->json('data');

        $this->assertFalse($preview['free_delivery']['qualifies']);
        $this->assertEquals(
            (float) $cart['free_delivery']['remaining'],
            (float) $preview['free_delivery']['remaining'],
        );
        $this->assertEquals(
            (float) $cart['discount_total'],
            (float) $preview['discount_total'],
        );
        $this->assertGreaterThan(0, (float) $preview['shipping_total']);
    }

    public function test_cart_add_accepts_variant_id_alias_and_returns_variant_id(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(199);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
                'product_variant_id' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.variant_id', null)
            ->assertJsonMissingPath('data.items.0.product_variant_id');
    }

    public function test_quantity_zero_and_negative_rejected(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(199);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 0,
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => -1,
            ])
            ->assertStatus(422);
    }

    public function test_insufficient_stock_rejected(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(199, qty: 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertStatus(409);
    }

    public function test_client_unit_price_ignored_server_price_used(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(249);

        $cart = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertEquals(249.0, (float) $cart['items'][0]['unit_price']);
        $this->assertEquals(249.0, (float) $cart['subtotal']);
    }

    public function test_customer_cannot_modify_another_customers_cart_item(): void
    {
        $a = $this->seedCustomer('qa-cart-a@example.com');
        $b = $this->seedCustomer('qa-cart-b@example.com');
        $product = $this->makeSellableProduct(150);

        $tokenA = JWTAuth::fromUser($a);
        $add = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data');

        $itemId = (int) $add['items'][0]['id'];

        $tokenB = JWTAuth::fromUser($b);
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->putJson('/api/v1/cart/items/'.$itemId, ['quantity' => 2])
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->deleteJson('/api/v1/cart/items/'.$itemId)
            ->assertNotFound();
    }

    public function test_remove_coupon_restores_free_delivery_when_above_threshold(): void
    {
        putenv('FREE_DELIVERY_THRESHOLD=999');
        $_ENV['FREE_DELIVERY_THRESHOLD'] = '999';

        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(1100);
        $headers = ['Authorization' => 'Bearer '.$token];

        Coupon::query()->create([
            'code' => 'FLAT200',
            'name' => 'Flat 200',
            'discount_type' => 'fixed',
            'discount_value' => 200,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_public' => true,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $withCoupon = $this->withHeaders($headers)
            ->postJson('/api/v1/cart/apply-coupon', ['code' => 'FLAT200'])
            ->assertOk()
            ->json('data');

        // 1100 - 200 = 900 < 999
        $this->assertFalse($withCoupon['free_delivery']['qualifies']);

        $cleared = $this->withHeaders($headers)
            ->deleteJson('/api/v1/cart/coupon')
            ->assertOk()
            ->json('data');

        $this->assertTrue($cleared['free_delivery']['qualifies']);
        $this->assertEquals(0.0, (float) $cleared['discount_total']);
    }

    public function test_invalid_coupon_rejected(): void
    {
        $user = $this->seedCustomer();
        $token = JWTAuth::fromUser($user);
        $product = $this->makeSellableProduct(500);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/apply-coupon', ['code' => 'NOPE'])
            ->assertStatus(400);
    }
}
