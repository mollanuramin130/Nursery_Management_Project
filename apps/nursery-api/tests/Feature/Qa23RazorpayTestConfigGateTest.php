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
use App\Shared\Support\ProductionReadinessChecker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-23 — Razorpay TEST configuration gate (no LIVE claim).
 *
 * Asserts env contract + empty-credential behavior on this host.
 * Does NOT call Razorpay network APIs with real money.
 */
class Qa23RazorpayTestConfigGateTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_project_uses_established_razorpay_env_names(): void
    {
        // Canonical names in this repo (NOT RAZORPAY_KEY_ID / KEY_SECRET).
        $example = file_get_contents(base_path('.env.example'));
        $this->assertIsString($example);
        $this->assertStringContainsString('RAZORPAY_KEY=', $example);
        $this->assertStringContainsString('RAZORPAY_SECRET=', $example);
        $this->assertStringContainsString('RAZORPAY_WEBHOOK_SECRET=', $example);
        $this->assertStringNotContainsString('RAZORPAY_KEY_ID=', $example);
        $this->assertStringNotContainsString('RAZORPAY_KEY_SECRET=', $example);
    }

    public function test_empty_credentials_use_local_stub_gateway_create(): void
    {
        $this->clearRazorpayEnv();

        if (app()->environment('production')) {
            $this->markTestSkipped('Production must not use stub create');
        }

        $gateway = app(RazorpayGateway::class);
        $created = $gateway->createOrder(100.0, 'INR', 'rcpt-qa23', ['order_id' => 1]);
        $this->assertSame('local_stub', $created['client_payload']['mode'] ?? null);
        $this->assertArrayNotHasKey('secret', $created['client_payload']);
        $encoded = json_encode($created);
        $this->assertStringNotContainsString('RAZORPAY_SECRET', (string) $encoded);
    }

    public function test_webhook_route_exists_and_rejects_when_secret_missing_and_unsigned_disallowed(): void
    {
        // phpunit.xml defaults ALLOW_UNSIGNED=true for local simulation suites.
        // Override for this gate: empty secret + unsigned false → 503.
        putenv('RAZORPAY_WEBHOOK_SECRET=');
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false');
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';

        $this->postJson('/api/v1/payments/webhooks/razorpay', ['event' => 'payment.captured'])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_initiate_payload_never_includes_secret_when_stub(): void
    {
        $this->clearRazorpayEnv();

        $user = User::query()->create([
            'name' => 'QA23 Cust',
            'email' => 'qa23-cust-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA23 Plant',
            'slug' => 'qa23-plant-'.uniqid(),
            'sku' => 'QA23-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 200,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA23'],
            ['name' => 'QA23', 'status' => 'active', 'is_default' => true],
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
            ['code' => 'STD-QA23'],
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
            'name' => 'QA23',
            'phone' => '9000000000',
            'line1' => '1 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $orderId = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa23-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'upi',
            ])
            ->assertCreated()
            ->json('data.id');

        $payload = $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $orderId,
                'method' => 'upi',
                'mode' => 'dynamic_qr',
            ])
            ->assertOk()
            ->json('data');

        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('RAZORPAY_SECRET', (string) $encoded);
        $this->assertStringNotContainsString('webhook_secret', strtolower((string) $encoded));
        $this->assertArrayNotHasKey('secret', $payload['client_payload'] ?? []);
    }

    public function test_production_readiness_rejects_rzp_test_key_in_production(): void
    {
        $checker = app(ProductionReadinessChecker::class);
        $findings = $checker->evaluate('production', false, [
            'razorpay_key' => 'rzp_test_PLACEHOLDER_NOT_A_REAL_SECRET',
            'razorpay_secret' => 'secret_placeholder',
            'razorpay_webhook_secret' => 'whsec_placeholder',
        ]);
        $codes = array_column($findings, 'code');
        $this->assertContains('RAZORPAY_TEST_KEY_IN_PRODUCTION', $codes);
    }
}
