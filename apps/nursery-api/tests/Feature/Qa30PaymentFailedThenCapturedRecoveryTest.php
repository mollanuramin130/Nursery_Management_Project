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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-30 — payment.failed then payment.captured must still CONFIRM the order.
 */
class Qa30PaymentFailedThenCapturedRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        putenv('RAZORPAY_KEY=');
        putenv('RAZORPAY_SECRET=');
        putenv('RAZORPAY_WEBHOOK_SECRET=qa30_webhook_secret');
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false');
        $_ENV['RAZORPAY_KEY'] = '';
        $_ENV['RAZORPAY_SECRET'] = '';
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = 'qa30_webhook_secret';
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
        $_SERVER['RAZORPAY_KEY'] = '';
        $_SERVER['RAZORPAY_SECRET'] = '';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = 'qa30_webhook_secret';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
    }

    public function test_failed_webhook_then_captured_confirms_order_and_commits_stock_once(): void
    {
        $user = User::query()->create([
            'name' => 'QA30 Cust',
            'email' => 'qa30-cust@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));
        $token = JWTAuth::fromUser($user);

        $product = Product::query()->create([
            'name' => 'QA30 Plant',
            'slug' => 'qa30-plant-'.uniqid(),
            'sku' => 'QA30-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 100,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA30'],
            ['name' => 'QA30 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 5,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA30',
            'phone' => '9888888888',
            'line1' => '30 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA30-'.uniqid(),
            'name' => 'Standard',
            'price' => 0,
            'status' => 'active',
            'eta_min_days' => 1,
            'eta_max_days' => 3,
        ]);

        $order = $this->withToken($token)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'razorpay',
            ])
            ->assertSuccessful()
            ->json('data');
        $orderId = (int) ($order['id'] ?? $order['order']['id']);

        $pay = $this->withToken($token)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $orderId,
                'method' => 'razorpay',
            ])
            ->assertSuccessful()
            ->json('data');

        $providerOrderId = (string) $pay['provider_order_id'];
        $paymentId = (int) $pay['payment_id'];

        $this->postSignedWebhook([
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_fail_qa30',
                        'order_id' => $providerOrderId,
                        'status' => 'failed',
                        'amount' => 10000,
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame('PAYMENT_FAILED', Order::query()->find($orderId)?->status);
        $this->assertSame('failed', Payment::query()->find($paymentId)?->status);

        $this->postSignedWebhook([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_ok_qa30',
                        'order_id' => $providerOrderId,
                        'status' => 'captured',
                        'amount' => 10000,
                    ],
                ],
            ],
        ])->assertOk();

        $order = Order::query()->findOrFail($orderId);
        $payment = Payment::query()->findOrFail($paymentId);
        $this->assertSame('CONFIRMED', $order->status);
        $this->assertSame('success', $payment->status);
        $this->assertSame('pay_ok_qa30', $payment->provider_payment_id);
        $this->assertNull($order->cancelled_at);

        $inv = InventoryItem::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(4, (int) $inv->qty_on_hand);
        $this->assertSame(0, (int) $inv->qty_reserved);

        // Idempotent second capture must not double-commit stock.
        $this->postSignedWebhook([
            'event' => 'order.paid',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_ok_qa30',
                        'order_id' => $providerOrderId,
                        'status' => 'captured',
                        'amount' => 10000,
                    ],
                ],
            ],
        ])->assertOk();

        $inv->refresh();
        $this->assertSame(4, (int) $inv->qty_on_hand);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);
    }

    private function postSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($raw);
        $sig = hash_hmac('sha256', $raw, 'qa30_webhook_secret');

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
}
