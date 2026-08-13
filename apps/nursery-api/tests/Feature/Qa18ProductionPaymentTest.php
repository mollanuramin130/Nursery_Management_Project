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
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Shared\Exceptions\ApiException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-18 — Production payment path automation (local stub / signed webhook).
 *
 * Does NOT claim Razorpay LIVE PASS. Live credentials were EMPTY on the QA-18 host.
 */
class Qa18ProductionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Ensure local stub path (no secrets printed).
        putenv('RAZORPAY_KEY=');
        putenv('RAZORPAY_SECRET=');
        putenv('RAZORPAY_WEBHOOK_SECRET=');
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false');
        $_ENV['RAZORPAY_KEY'] = '';
        $_ENV['RAZORPAY_SECRET'] = '';
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
        $_SERVER['RAZORPAY_KEY'] = '';
        $_SERVER['RAZORPAY_SECRET'] = '';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
    }

    private function customer(): User
    {
        $user = User::query()->create([
            'name' => 'QA18 Cust',
            'email' => 'qa18-cust@example.com',
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
            'name' => 'QA18 Admin',
            'email' => 'qa18-admin@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function product(int $qty = 10): Product
    {
        $product = Product::query()->create([
            'name' => 'QA18 Plant',
            'slug' => 'qa18-plant-'.uniqid(),
            'sku' => 'QA18-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 250,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA18'],
            ['name' => 'QA18 WH', 'status' => 'active'],
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

    /**
     * @return array{customer: User, token: string, orderId: int, product: Product}
     */
    private function placePendingRazorpayOrder(?User $customer = null, ?Product $product = null): array
    {
        $customer ??= $this->customer();
        $product ??= $this->product();
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA18',
            'phone' => '9888888888',
            'line1' => '18 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA18-'.uniqid(),
            'name' => 'Standard',
            'price' => 40,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout/preview', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
            ])
            ->assertOk();

        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa18_rz_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'razorpay',
            ])
            ->assertSuccessful()
            ->json('data');

        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertGreaterThan(0, $orderId);
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($orderId)?->status);

        return [
            'customer' => $customer,
            'token' => $token,
            'orderId' => $orderId,
            'product' => $product,
        ];
    }

    private function postSignedWebhook(array $payload, string $secret, ?string $overrideSignature = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($raw);
        $sig = $overrideSignature ?? hash_hmac('sha256', $raw, $secret);

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

    public function test_initiate_verify_success_confirms_order(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $token = $ctx['token'];
        $orderId = $ctx['orderId'];

        $init = $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', ['order_id' => $orderId, 'method' => 'razorpay'])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame('local_stub', data_get($init, 'client_payload.mode'));

        $paymentId = (int) ($init['payment_id'] ?? 0);
        // provider_order_id is the Razorpay order id — NOT the internal nursery order_id.
        $providerOrderId = (string) ($init['provider_order_id'] ?? data_get($init, 'client_payload.order_id') ?? '');
        $this->assertGreaterThan(0, $paymentId);
        $this->assertNotSame('', $providerOrderId);
        $this->assertNotSame((string) $orderId, $providerOrderId);

        $providerPaymentId = 'pay_local_'.uniqid();
        $signature = 'local_'.$providerOrderId;

        $this->withToken($token)
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $paymentId,
                'provider_payment_id' => $providerPaymentId,
                'provider_order_id' => $providerOrderId,
                'provider_signature' => $signature,
            ])
            ->assertSuccessful();

        $this->assertSame('success', Payment::query()->find($paymentId)?->status);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);

        // Idempotent verify replay
        $this->withToken($token)
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $paymentId,
                'provider_payment_id' => $providerPaymentId,
                'provider_order_id' => $providerOrderId,
                'provider_signature' => $signature,
            ])
            ->assertSuccessful();
        $this->assertSame(1, Payment::query()->where('order_id', $orderId)->where('status', 'success')->count());
    }

    public function test_invalid_signature_does_not_mark_order_paid(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $token = $ctx['token'];
        $orderId = $ctx['orderId'];

        $init = $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', ['order_id' => $orderId])
            ->assertSuccessful()
            ->json('data');
        $paymentId = (int) $init['payment_id'];
        $providerOrderId = (string) $init['provider_order_id'];

        $this->withToken($token)
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $paymentId,
                'provider_payment_id' => 'pay_bad',
                'provider_order_id' => $providerOrderId,
                'provider_signature' => 'not_a_valid_local_signature',
            ])
            ->assertStatus(400);

        $this->assertNotSame('success', Payment::query()->find($paymentId)?->status);
        $this->assertContains(Order::query()->find($orderId)?->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED']);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $secret = 'qa18_webhook_secret_test';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $ctx = $this->placePendingRazorpayOrder();
        $token = $ctx['token'];
        $orderId = $ctx['orderId'];

        $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', ['order_id' => $orderId])
            ->assertSuccessful();
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_wh_'.uniqid(),
                        'order_id' => $payment->provider_order_id,
                        'amount' => (int) round(((float) $payment->amount) * 100),
                        'currency' => 'INR',
                        'status' => 'captured',
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($payload, $secret)->assertSuccessful();
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);

        $this->postSignedWebhook($payload, $secret)
            ->assertSuccessful()
            ->assertJsonPath('data.idempotent', true);
        $this->assertSame(1, Payment::query()->where('order_id', $orderId)->where('status', 'success')->count());
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);
    }

    public function test_invalid_webhook_signature_rejected(): void
    {
        $secret = 'qa18_webhook_secret_test';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $ctx = $this->placePendingRazorpayOrder();
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful();
        $payment = Payment::query()->where('order_id', $ctx['orderId'])->firstOrFail();

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_bad_sig',
                        'order_id' => $payment->provider_order_id,
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($payload, $secret, 'totally_wrong_signature')
            ->assertStatus(401);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);
    }

    public function test_amount_mismatch_rejects_finalize(): void
    {
        $secret = 'qa18_webhook_secret_test';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $customer = $this->customer();
        $order = Order::query()->create([
            'order_number' => 'GL-QA18-AMT-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'razorpay',
            'shipping_address_json' => ['line1' => 'A'],
            'billing_address_json' => ['line1' => 'A'],
            'placed_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'razorpay',
            'method' => 'razorpay',
            'amount' => 50, // mismatch vs order grand_total
            'currency' => 'INR',
            'status' => 'pending',
            'provider_order_id' => 'order_amt_mismatch_'.uniqid(),
            'idempotency_key' => 'qa18-amt-'.uniqid(),
        ]);

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_amt_bad',
                        'order_id' => $payment->provider_order_id,
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($payload, $secret)->assertStatus(409);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', $order->fresh()->status);
    }

    public function test_payment_failed_webhook_marks_failed_not_paid(): void
    {
        $secret = 'qa18_webhook_secret_test';
        putenv('RAZORPAY_WEBHOOK_SECRET='.$secret);
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = $secret;
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = $secret;

        $ctx = $this->placePendingRazorpayOrder();
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful();
        $payment = Payment::query()->where('order_id', $ctx['orderId'])->firstOrFail();

        $payload = [
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_fail_'.uniqid(),
                        'order_id' => $payment->provider_order_id,
                        'status' => 'failed',
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($payload, $secret)->assertSuccessful();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertNotSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);
        $this->assertNotSame('success', $payment->fresh()->status);
    }

    public function test_cod_regression_and_fulfillment_to_delivered(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $product = $this->product(5);
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA18 COD',
            'phone' => '9777777777',
            'line1' => 'COD St',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'COD-QA18',
            'name' => 'Standard',
            'price' => 30,
            'status' => 'active',
            'eta_min_days' => 1,
            'eta_max_days' => 3,
        ]);

        $this->withToken($token)->postJson('/api/v1/checkout/preview', [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
        ])->assertOk();

        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa18_cod_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])
            ->assertSuccessful()
            ->json('data');
        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);

        // COD is already CONFIRMED — initiate must refuse (not awaiting payment).
        $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', ['order_id' => $orderId])
            ->assertStatus(409);

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
        ])->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/ship", [
            'carrier' => 'manual',
            'tracking_number' => 'QA18-COD-1',
        ])->assertSuccessful();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/out-for-delivery")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/deliver", [
            'method' => 'note',
            'note' => 'QA18 COD delivered',
        ])->assertOk()->assertJsonPath('data.status', 'DELIVERED');
    }

    public function test_production_stub_rejected_and_unauthorized_payment_access(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        putenv('RAZORPAY_KEY=');
        putenv('RAZORPAY_SECRET=');
        $_ENV['RAZORPAY_KEY'] = '';
        $_ENV['RAZORPAY_SECRET'] = '';

        $gateway = new RazorpayGateway;
        try {
            $gateway->createOrder(10, 'INR', 'ORD-QA18-STUB');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('PAYMENT_GATEWAY_UNAVAILABLE', $e->errorCode());
        }

        // Reset env for HTTP auth checks under testing.
        $this->app->detectEnvironment(fn () => 'testing');

        $ctx = $this->placePendingRazorpayOrder();
        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'qa18-other@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $other->forceFill(['status' => 'active'])->save();
        $other->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->actingAs($other->fresh(), 'api')
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertNotFound();
    }

    public function test_unauthenticated_payment_endpoints_rejected(): void
    {
        $this->postJson('/api/v1/payments/initiate', ['order_id' => 1])->assertUnauthorized();
        $this->postJson('/api/v1/payments/verify', [
            'payment_id' => 1,
            'provider_payment_id' => 'x',
            'provider_order_id' => 'y',
            'provider_signature' => 'z',
        ])->assertUnauthorized();
        $this->getJson('/api/v1/payments/1')->assertUnauthorized();
    }

    public function test_paid_razorpay_order_can_fulfill_to_delivered(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $admin = $this->admin();
        $token = $ctx['token'];
        $orderId = $ctx['orderId'];
        $product = $ctx['product'];

        $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', ['order_id' => $orderId])
            ->assertSuccessful();
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $payment->id,
                'provider_payment_id' => 'pay_fulfill_'.uniqid(),
                'provider_order_id' => $payment->provider_order_id,
                'provider_signature' => 'local_'.$payment->provider_order_id,
            ])
            ->assertSuccessful();
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);

        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->actingAs($admin->fresh(['roles.permissions']), 'api')
            ->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/start")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/scan", [
            'code' => $product->sku,
            'increment_by' => 1,
        ])->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pick/complete")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/pack", [
            'package_count' => 1,
        ])->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/ship", [
            'carrier' => 'manual',
            'tracking_number' => 'QA18-RZ-1',
        ])->assertSuccessful();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/out-for-delivery")->assertOk();
        $this->actingAs($admin, 'api')->postJson("/api/v1/admin/fulfillment/orders/{$orderId}/deliver", [
            'method' => 'note',
            'note' => 'QA18 RZ delivered',
        ])->assertOk()->assertJsonPath('data.status', 'DELIVERED');
    }

    public function test_webhook_secret_required_when_unsigned_disallowed(): void
    {
        putenv('RAZORPAY_WEBHOOK_SECRET=');
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = '';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = '';
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false');
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_x',
                        'order_id' => 'order_x',
                    ],
                ],
            ],
        ];

        $this->postJson('/api/v1/payments/webhooks/razorpay', $payload)
            ->assertStatus(503);
    }
}
