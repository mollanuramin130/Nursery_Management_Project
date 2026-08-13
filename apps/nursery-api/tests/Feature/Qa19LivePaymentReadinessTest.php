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
 * QA-19 — Live payment readiness gaps (automated provider simulation).
 *
 * Does NOT claim Razorpay LIVE / UPI device PASS. Credentials were EMPTY on QA-19 host.
 */
class Qa19LivePaymentReadinessTest extends TestCase
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

    private function customer(): User
    {
        $user = User::query()->create([
            'name' => 'QA19 Cust',
            'email' => 'qa19-cust@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function product(int $qty = 10): Product
    {
        $product = Product::query()->create([
            'name' => 'QA19 Plant',
            'slug' => 'qa19-plant-'.uniqid(),
            'sku' => 'QA19-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 180,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA19'],
            ['name' => 'QA19 WH', 'status' => 'active'],
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
     * @return array{token: string, orderId: int, customer: User}
     */
    private function placePendingRazorpayOrder(): array
    {
        $customer = $this->customer();
        $product = $this->product();
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA19',
            'phone' => '9666666666',
            'line1' => '19 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA19-'.uniqid(),
            'name' => 'Standard',
            'price' => 35,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 4,
        ]);

        $this->withToken($token)->postJson('/api/v1/checkout/preview', [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
        ])->assertOk();

        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa19_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'razorpay',
            ])
            ->assertSuccessful()
            ->json('data');

        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($orderId)?->status);

        return ['token' => $token, 'orderId' => $orderId, 'customer' => $customer];
    }

    public function test_duplicate_initiate_reuses_pending_provider_order(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $a = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');
        $b = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame($a['payment_id'], $b['payment_id']);
        $this->assertSame($a['provider_order_id'], $b['provider_order_id']);
        $this->assertSame(1, Payment::query()->where('order_id', $ctx['orderId'])->where('status', 'pending')->count());
    }

    public function test_already_paid_order_rejects_new_initiate(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_qa19_'.uniqid(),
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        $this->assertSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertStatus(409);
    }

    public function test_verify_rejects_wrong_provider_order_id(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_wrong',
                'provider_order_id' => 'order_completely_wrong',
                'provider_signature' => 'local_order_completely_wrong',
            ])
            ->assertStatus(400);

        $this->assertSame('PENDING_PAYMENT', Order::query()->find($ctx['orderId'])?->status);
        $this->assertNotSame('success', Payment::query()->find($init['payment_id'])?->status);
    }

    public function test_cancelled_order_is_non_payable(): void
    {
        $ctx = $this->placePendingRazorpayOrder();

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/orders/'.$ctx['orderId'].'/cancel', [
                'reason' => 'QA19 cancel before pay',
            ])
            ->assertSuccessful();

        $this->assertSame('CANCELLED', Order::query()->find($ctx['orderId'])?->status);

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertStatus(409);
    }

    public function test_inventory_committed_exactly_once_on_paid_path(): void
    {
        $ctx = $this->placePendingRazorpayOrder();
        $order = Order::query()->with('items')->findOrFail($ctx['orderId']);
        $productId = (int) $order->items->first()->product_id;
        $before = InventoryItem::query()->where('product_id', $productId)->firstOrFail();
        $reservedBefore = (int) $before->qty_reserved;
        $onHandBefore = (int) $before->qty_on_hand;

        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', ['order_id' => $ctx['orderId']])
            ->assertSuccessful()
            ->json('data');

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_inv_'.uniqid(),
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        // Idempotent verify replay must not double-commit.
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_inv_replay',
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        $after = InventoryItem::query()->where('product_id', $productId)->firstOrFail();
        $this->assertSame($onHandBefore - 1, (int) $after->qty_on_hand);
        $this->assertSame(max(0, $reservedBefore - 1), (int) $after->qty_reserved);
        $this->assertSame(1, Payment::query()->where('order_id', $ctx['orderId'])->where('status', 'success')->count());
    }

    public function test_readiness_blocks_test_keys_in_production_profile(): void
    {
        $checker = new ProductionReadinessChecker;
        $codes = array_column($checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_abc',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
            'jwt_secret' => 'jwt',
        ]), 'code');
        $this->assertContains('RAZORPAY_TEST_KEY_IN_PRODUCTION', $codes);
    }
}
