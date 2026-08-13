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
use App\Modules\Payment\Models\Payment;
use App\Shared\Support\ProductionReadinessChecker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-20 — Production release gate (automated simulation + readiness).
 *
 * Does NOT claim Razorpay LIVE PASS. Host credentials were EMPTY.
 */
class Qa20ProductionReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        putenv('RAZORPAY_KEY=');
        putenv('RAZORPAY_SECRET=');
        putenv('RAZORPAY_WEBHOOK_SECRET=');
        $_ENV['RAZORPAY_KEY'] = '';
        $_ENV['RAZORPAY_SECRET'] = '';
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_SERVER['RAZORPAY_KEY'] = '';
        $_SERVER['RAZORPAY_SECRET'] = '';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = '';
    }

    private function customer(string $email = 'qa20-cust@example.com'): User
    {
        $user = User::query()->create([
            'name' => 'QA20 Cust',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function product(): Product
    {
        $product = Product::query()->create([
            'name' => 'QA20 Plant',
            'slug' => 'qa20-plant-'.uniqid(),
            'sku' => 'QA20-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 220,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA20'],
            ['name' => 'QA20 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 12,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    /**
     * @return array{token: string, orderId: int, customer: User}
     */
    private function placePending(): array
    {
        $customer = $this->customer('qa20-'.uniqid().'@example.com');
        $product = $this->product();
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA20',
            'phone' => '9555555555',
            'line1' => '20 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA20-'.uniqid(),
            'name' => 'Standard',
            'price' => 25,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $this->withToken($token)->postJson('/api/v1/checkout/preview', [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
        ])->assertOk();

        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa20_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'razorpay',
            ])
            ->assertSuccessful()
            ->json('data');

        return [
            'token' => $token,
            'orderId' => (int) ($order['id'] ?? $order['order']['id'] ?? 0),
            'customer' => $customer,
        ];
    }

    private function postSignedWebhook(array $payload, string $secret): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $raw, $secret);

        return $this->call(
            'POST',
            '/api/v1/payments/webhooks/razorpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
            ],
            $raw,
        );
    }

    public function test_initiate_client_payload_never_exposes_secret(): void
    {
        $ctx = $this->placePending();
        $data = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $encoded = json_encode($data);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('RAZORPAY_SECRET', $encoded);
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('key_secret', $data['client_payload'] ?? []);
        $this->assertSame('local_stub', data_get($data, 'client_payload.mode'));
    }

    public function test_webhook_after_client_verify_is_idempotent(): void
    {
        $secret = 'qa20_wh_secret';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $ctx = $this->placePending();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_client_first',
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        $this->assertSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_webhook_late',
                        'order_id' => $init['provider_order_id'],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($payload, $secret)
            ->assertSuccessful()
            ->assertJsonPath('data.idempotent', true);

        $this->assertSame(1, Payment::query()->where('order_id', $ctx['orderId'])->where('status', 'success')->count());
        $this->assertSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);
    }

    public function test_other_user_cannot_verify_payment(): void
    {
        $ctx = $this->placePending();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $other = $this->customer('qa20-other@example.com');
        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->actingAs($other, 'api')
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_idor',
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertNotFound();

        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);
    }

    public function test_invalid_payment_id_on_verify_rejected(): void
    {
        $ctx = $this->placePending();
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => 999999,
                'provider_payment_id' => 'pay_x',
                'provider_order_id' => 'order_x',
                'provider_signature' => 'local_order_x',
            ])
            ->assertNotFound();
    }

    public function test_retry_after_failed_payment_allows_new_initiate(): void
    {
        $secret = 'qa20_wh_secret';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $ctx = $this->placePending();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $failPayload = [
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_fail_retry',
                        'order_id' => $init['provider_order_id'],
                        'status' => 'failed',
                    ],
                ],
            ],
        ];
        $this->postSignedWebhook($failPayload, $secret)->assertSuccessful();
        $this->assertSame('failed', Payment::query()->find($init['payment_id'])?->status);
        $this->assertSame('PAYMENT_FAILED', Order::query()->find($ctx['orderId'])?->status);

        $retry = $this->withToken($ctx['token'])
            ->postJson('/api/v1/orders/'.$ctx['orderId'].'/retry-payment')
            ->assertSuccessful()
            ->json('data');

        $this->assertNotSame($init['payment_id'], $retry['payment_id']);
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);
        $this->assertSame('pending', Payment::query()->find($retry['payment_id'])?->status);
    }

    public function test_staging_may_use_test_keys_but_production_must_not(): void
    {
        $checker = new ProductionReadinessChecker;
        $staging = $checker->evaluate('staging', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_ok_for_staging',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://staging.example.com',
            'app_url' => 'https://api-staging.example.com',
            'jwt_secret' => 'jwt',
        ]);
        $this->assertSame([], $staging);

        $prod = array_column($checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_not_for_prod',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
            'jwt_secret' => 'jwt',
        ]), 'code');
        $this->assertContains('RAZORPAY_TEST_KEY_IN_PRODUCTION', $prod);
    }

    public function test_admin_cannot_force_payment_success_via_customer_verify(): void
    {
        // Super-admin using customer verify endpoint still cannot verify another user's payment.
        $ctx = $this->placePending();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $admin = User::query()->create([
            'name' => 'QA20 Admin',
            'email' => 'qa20-admin@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->forceFill(['status' => 'active'])->save();
        $admin->roles()->sync([$role->id]);

        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->actingAs($admin->fresh(['roles.permissions']), 'api')
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_admin_force',
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertNotFound();

        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);
    }
}
