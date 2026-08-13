<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase21WebhookHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_without_provider_order_id_does_not_bind_latest_payment(): void
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'Buyer',
            'email' => 'wh-buyer@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();

        $order = Order::query()->create([
            'order_number' => 'GL-WH-'.uniqid(),
            'user_id' => $user->id,
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

        Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => 'razorpay',
            'method' => 'online',
            'amount' => 100,
            'currency' => 'INR',
            'status' => 'pending',
            'provider_order_id' => 'order_SHOULD_NOT_MATCH',
            'idempotency_key' => 'phase21-'.uniqid(),
        ]);

        // Unsigned allowed in testing via env; payload has payment entity without order_id.
        config(['app.env' => 'testing']);
        putenv('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=true');
        $_ENV['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'true';
        $_SERVER['PAYMENT_ALLOW_UNSIGNED_WEBHOOKS'] = 'true';

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_orphan',
                        // intentionally no order_id
                    ],
                ],
            ],
        ];

        $res = $this->postJson('/api/v1/payments/webhooks/razorpay', $payload);
        $res->assertOk();
        $this->assertFalse((bool) data_get($res->json(), 'data.handled'));

        $this->assertSame('pending', Payment::query()->where('order_id', $order->id)->value('status'));
    }
}
