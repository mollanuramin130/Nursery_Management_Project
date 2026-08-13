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
 * QA-31 — amount authority on pending reuse + failure→retry inventory once.
 */
class Qa31AmountAuthorityAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        putenv('RAZORPAY_KEY=');
        putenv('RAZORPAY_SECRET=');
        putenv('RAZORPAY_WEBHOOK_SECRET=qa31_webhook_secret');
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false');
        $_ENV['RAZORPAY_KEY'] = '';
        $_ENV['RAZORPAY_SECRET'] = '';
        $_ENV['RAZORPAY_WEBHOOK_SECRET'] = 'qa31_webhook_secret';
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
        $_SERVER['RAZORPAY_KEY'] = '';
        $_SERVER['RAZORPAY_SECRET'] = '';
        $_SERVER['RAZORPAY_WEBHOOK_SECRET'] = 'qa31_webhook_secret';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'false';
    }

    public function test_client_amount_override_rejected_even_when_pending_payment_exists(): void
    {
        $ctx = $this->placePendingUpiOrder();
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'dynamic_qr',
            ])
            ->assertSuccessful();

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'dynamic_qr',
                'amount' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment amount mismatch');
    }

    public function test_failed_then_retry_then_success_commits_inventory_once(): void
    {
        $ctx = $this->placePendingUpiOrder();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'checkout',
            ])
            ->assertSuccessful()
            ->json('data');

        $providerOrderId = (string) $init['provider_order_id'];
        $order = Order::query()->with('items')->findOrFail($ctx['orderId']);
        $productId = (int) $order->items->first()->product_id;
        $before = InventoryItem::query()->where('product_id', $productId)->firstOrFail();

        $this->postSignedWebhook([
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_fail_qa31',
                        'order_id' => $providerOrderId,
                        'status' => 'failed',
                        'amount' => (int) round($ctx['grandTotal'] * 100),
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame('PAYMENT_FAILED', Order::query()->find($ctx['orderId'])?->status);

        $retry = $this->withToken($ctx['token'])
            ->postJson('/api/v1/orders/'.$ctx['orderId'].'/retry-payment')
            ->assertSuccessful()
            ->json('data');

        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $retry['payment_id'],
                'provider_order_id' => $retry['provider_order_id'],
                'provider_payment_id' => 'pay_ok_qa31',
                'provider_signature' => 'local_'.$retry['provider_order_id'],
            ])
            ->assertSuccessful();

        $this->assertSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);
        $this->assertSame(1, Payment::query()->where('order_id', $ctx['orderId'])->where('status', 'success')->count());

        $after = InventoryItem::query()->where('product_id', $productId)->firstOrFail();
        $this->assertSame((int) $before->qty_on_hand - 1, (int) $after->qty_on_hand);
    }

    /**
     * @return array{token:string,orderId:int,grandTotal:float}
     */
    private function placePendingUpiOrder(): array
    {
        $user = User::query()->create([
            'name' => 'QA31 Cust',
            'email' => 'qa31-cust@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));
        $token = JWTAuth::fromUser($user);

        $product = Product::query()->create([
            'name' => 'QA31 Plant',
            'slug' => 'qa31-plant-'.uniqid(),
            'sku' => 'QA31-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 80,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA31'],
            ['name' => 'QA31 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 6,
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
            'name' => 'QA31',
            'phone' => '9888888888',
            'line1' => '31 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA31-'.uniqid(),
            'name' => 'Standard',
            'price' => 0,
            'status' => 'active',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $order = $this->withToken($token)
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'upi',
            ])
            ->assertSuccessful()
            ->json('data');

        $orderId = (int) ($order['id'] ?? $order['order']['id']);

        return [
            'token' => $token,
            'orderId' => $orderId,
            'grandTotal' => (float) (Order::query()->find($orderId)?->grand_total ?? 0),
        ];
    }

    private function postSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($raw);
        $sig = hash_hmac('sha256', $raw, 'qa31_webhook_secret');

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
