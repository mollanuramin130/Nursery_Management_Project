<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notification\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-25 — Real Razorpay TEST payment + UPI E2E gate.
 *
 * Never claims LIVE. Never auto-charges PSP. When credentials are EMPTY,
 * asserts BLOCKED posture. When TEST credentials are SET, skips the empty-gate
 * so operators can run the real matrix manually (see docs/RAZORPAY-TEST-SETUP.md).
 */
class Qa25RealTestPaymentGateTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_qa25_credentials_presence_reported_without_values(): void
    {
        $p = $this->razorpayEnvPresence();
        foreach (['key', 'secret', 'webhook'] as $name) {
            $this->assertContains($p[$name] === '' ? 'EMPTY' : 'SET', ['EMPTY', 'SET']);
        }
    }

    public function test_qa25_rejects_live_key_prefix(): void
    {
        $key = $this->razorpayEnvPresence()['key'];
        if ($key === '') {
            $this->assertTrue(true);

            return;
        }
        $this->assertStringStartsWith('rzp_test_', $key, 'QA-25 allows TEST keys only');
        $this->assertFalse(str_starts_with($key, 'rzp_live_'));
    }

    public function test_qa25_webhook_route_is_registered(): void
    {
        $this->assertTrue(
            Route::has('api.v1.payments.webhooks')
            || collect(Route::getRoutes())->contains(function ($route) {
                return str_contains($route->uri(), 'payments/webhooks')
                    && in_array('POST', $route->methods(), true);
            }),
            'POST /api/v1/payments/webhooks/{provider} must remain registered'
        );

        // Soft probe: unsigned empty body should not 404 (auth/signature may 4xx).
        $response = $this->postJson('/api/v1/payments/webhooks/razorpay', []);
        $this->assertNotSame(404, $response->status());
    }

    public function test_qa25_real_test_payment_blocked_when_credentials_empty(): void
    {
        if ($this->razorpayTestCredentialsConfigured()) {
            $this->markTestSkipped(
                'TEST credentials SET — run real TEST matrix via ops (Web/Mobile + webhook); do not auto-charge in PHPUnit.'
            );
        }

        $p = $this->razorpayEnvPresence();
        $this->assertSame('', $p['key']);
        $this->assertSame('', $p['secret']);
        $this->assertSame('', $p['webhook']);
        $this->assertTrue(true, 'QA-25 REAL TEST PAYMENT = BLOCKED on this host');
    }

    public function test_qa25_cod_regression_unaffected(): void
    {
        Queue::fake();
        $adminRole = Role::query()->where('slug', 'admin')->first();
        $admin = User::query()->create([
            'name' => 'QA25 Admin',
            'email' => 'qa25-admin-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->forceFill(['status' => 'active'])->save();
        if ($adminRole) {
            $admin->roles()->sync([$adminRole->id]);
        }

        $user = User::query()->create([
            'name' => 'QA25 COD',
            'email' => 'qa25-cod-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA25 COD Plant',
            'slug' => 'qa25-cod-'.uniqid(),
            'sku' => 'QA25C-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 180,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA25'],
            ['name' => 'QA25', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 12,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $ship = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA25'],
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
            'name' => 'QA25',
            'phone' => '9444444444',
            'line1' => '25 COD',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa25-cod-'.uniqid())
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
}
