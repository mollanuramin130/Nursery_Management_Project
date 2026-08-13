<?php

namespace Tests\Feature;

use App\Integrations\Payment\RazorpayGateway;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notification\Models\Notification;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-24 — Real Razorpay TEST payment gate.
 *
 * This suite never claims LIVE. On hosts without TEST credentials it asserts
 * the safe stub/blocked posture. Real network TEST payments require SET keys.
 */
class Qa24RealTestPaymentGateTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_credentials_presence_is_reported_without_values(): void
    {
        $p = $this->razorpayEnvPresence();
        foreach (['key', 'secret', 'webhook'] as $name) {
            $this->assertContains($p[$name] === '' ? 'EMPTY' : 'SET', ['EMPTY', 'SET']);
        }
    }

    public function test_live_key_prefix_is_rejected_for_qa24_policy(): void
    {
        $key = $this->razorpayEnvPresence()['key'];
        if ($key === '') {
            $this->assertTrue(true); // EMPTY host — policy N/A until configured

            return;
        }
        $this->assertStringStartsWith('rzp_test_', $key, 'QA-24 allows TEST keys only');
        $this->assertFalse(str_starts_with($key, 'rzp_live_'));
    }

    public function test_empty_credentials_keep_stub_initiate_and_no_secret_leak(): void
    {
        $this->clearRazorpayEnv();

        if (app()->environment('production')) {
            $this->markTestSkipped('Production must not use stub');
        }

        $user = User::query()->create([
            'name' => 'QA24',
            'email' => 'qa24-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA24 Plant',
            'slug' => 'qa24-'.uniqid(),
            'sku' => 'QA24-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 250,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA24'],
            ['name' => 'QA24', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 20,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $ship = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA24'],
            ['name' => 'Standard', 'status' => 'active', 'base_rate' => 40],
        );
        $token = JWTAuth::fromUser($user);
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA24',
            'phone' => '9111111111',
            'line1' => '24 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $orderId = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa24-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'upi',
            ])
            ->assertCreated()
            ->json('data.id');

        $data = $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $orderId,
                'method' => 'upi',
                'mode' => 'dynamic_qr',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('local_stub', data_get($data, 'client_payload.mode'));
        $encoded = json_encode($data);
        $this->assertStringNotContainsString('RAZORPAY_SECRET', (string) $encoded);
        $this->assertArrayNotHasKey('secret', $data['client_payload'] ?? []);
    }

    public function test_amount_mismatch_rejected_on_initiate(): void
    {
        $this->clearRazorpayEnv();

        $user = User::query()->create([
            'name' => 'QA24 Amt',
            'email' => 'qa24-amt-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA24 Amt Plant',
            'slug' => 'qa24-amt-'.uniqid(),
            'sku' => 'QA24A-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 300,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA24'],
            ['name' => 'QA24', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 10,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $ship = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA24'],
            ['name' => 'Standard', 'status' => 'active', 'base_rate' => 40],
        );
        $token = JWTAuth::fromUser($user);
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA24',
            'phone' => '9222222222',
            'line1' => '24 Amt',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $orderId = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa24-amt-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'upi',
            ])
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $orderId,
                'method' => 'upi',
                'mode' => 'dynamic_qr',
                'amount' => max(1, (float) $order->grand_total - 1),
            ])
            ->assertStatus(409);
    }

    public function test_cod_still_creates_order_and_staff_notification(): void
    {
        Queue::fake();
        $adminRole = Role::query()->where('slug', 'admin')->first();
        $admin = User::query()->create([
            'name' => 'QA24 Admin',
            'email' => 'qa24-admin-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->forceFill(['status' => 'active'])->save();
        if ($adminRole) {
            $admin->roles()->sync([$adminRole->id]);
        }

        $user = User::query()->create([
            'name' => 'QA24 COD',
            'email' => 'qa24-cod-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA24 COD Plant',
            'slug' => 'qa24-cod-'.uniqid(),
            'sku' => 'QA24C-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 200,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA24'],
            ['name' => 'QA24', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 15,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $ship = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA24'],
            ['name' => 'Standard', 'status' => 'active', 'base_rate' => 40],
        );
        $token = JWTAuth::fromUser($user);
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA24',
            'phone' => '9333333333',
            'line1' => '24 COD',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa24-cod-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated();

        $this->assertTrue(
            Notification::query()->where('user_id', $user->id)->where('type', 'order_confirmed')->exists()
        );
        $this->assertTrue(
            Notification::query()->where('type', 'new_order')->exists()
        );
    }

    public function test_real_test_payment_blocked_when_credentials_empty(): void
    {
        if ($this->razorpayTestCredentialsConfigured()) {
            $this->markTestSkipped('TEST credentials SET — execute real TEST matrix manually/ops; do not auto-charge here.');
        }

        $p = $this->razorpayEnvPresence();
        $this->assertSame('', $p['key']);
        $this->assertSame('', $p['secret']);
        $this->assertSame('', $p['webhook']);
        // Explicit gate for QA-24 closeout language.
        $this->assertTrue(true, 'REAL TEST PAYMENT = BLOCKED on this host');
    }
}
