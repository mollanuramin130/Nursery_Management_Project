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
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-21 — UPI Dynamic QR / Intent (automated simulation; not LIVE PSP PASS).
 */
class Qa21UpiPaymentTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->clearRazorpayEnv();
    }

    private function customer(): User
    {
        $user = User::query()->create([
            'name' => 'QA21 Cust',
            'email' => 'qa21-cust-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function product(): Product
    {
        $product = Product::query()->create([
            'name' => 'QA21 Plant',
            'slug' => 'qa21-plant-'.uniqid(),
            'sku' => 'QA21-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 300,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA21'],
            ['name' => 'QA21 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 20,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    /**
     * @return array{token: string, orderId: int, grandTotal: float}
     */
    private function placeUpiOrder(): array
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
            'name' => 'QA21',
            'phone' => '9444444444',
            'line1' => '21 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA21-'.uniqid(),
            'name' => 'Standard',
            'price' => 40,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 4,
        ]);

        $preview = $this->withToken($token)->postJson('/api/v1/checkout/preview', [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
        ])->assertOk()->json('data');

        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa21_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'upi',
            ])
            ->assertSuccessful()
            ->json('data');

        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($orderId)?->status);
        $this->assertSame('upi', Order::query()->find($orderId)?->payment_method);

        return [
            'token' => $token,
            'orderId' => $orderId,
            'grandTotal' => (float) ($preview['grand_total'] ?? Order::query()->find($orderId)?->grand_total),
        ];
    }

    public function test_upi_dynamic_qr_initiate_returns_qr_and_server_amount(): void
    {
        $ctx = $this->placeUpiOrder();
        $data = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'dynamic_qr',
            ])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame('upi', $data['method'] ?? null);
        $this->assertSame('dynamic_qr', $data['upi_mode'] ?? data_get($data, 'client_payload.upi_mode'));
        $this->assertNotEmpty(data_get($data, 'client_payload.qr_data'));
        $this->assertStringStartsWith('upi://pay?', (string) data_get($data, 'client_payload.qr_data'));
        $this->assertSame(
            (int) round($ctx['grandTotal'] * 100),
            (int) data_get($data, 'client_payload.amount'),
        );
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertTrue((bool) data_get($data, 'client_payload.stub_confirm_allowed'));
    }

    public function test_client_amount_override_rejected(): void
    {
        $ctx = $this->placeUpiOrder();
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'dynamic_qr',
                'amount' => 1,
            ])
            ->assertStatus(409);
    }

    public function test_upi_verify_confirms_and_commits_inventory_once(): void
    {
        $ctx = $this->placeUpiOrder();
        $init = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'dynamic_qr',
            ])
            ->assertSuccessful()
            ->json('data');

        $order = Order::query()->with('items')->findOrFail($ctx['orderId']);
        $productId = (int) $order->items->first()->product_id;
        $before = InventoryItem::query()->where('product_id', $productId)->firstOrFail();

        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_upi_'.uniqid(),
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        $this->assertSame('CONFIRMED', Order::query()->find($ctx['orderId'])?->status);
        $this->assertSame('success', Payment::query()->find($init['payment_id'])?->status);

        // Poll status after success.
        $this->withToken($ctx['token'])
            ->getJson('/api/v1/payments/'.$init['payment_id'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'success');

        // Duplicate verify — no double commit.
        $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/verify', [
                'payment_id' => $init['payment_id'],
                'provider_payment_id' => 'pay_upi_dup',
                'provider_order_id' => $init['provider_order_id'],
                'provider_signature' => 'local_'.$init['provider_order_id'],
            ])
            ->assertSuccessful();

        $after = InventoryItem::query()->where('product_id', $productId)->firstOrFail();
        $this->assertSame((int) $before->qty_on_hand - 1, (int) $after->qty_on_hand);
        $this->assertSame(1, Payment::query()->where('order_id', $ctx['orderId'])->where('status', 'success')->count());
    }

    public function test_upi_intent_initiate_returns_intent_url(): void
    {
        $ctx = $this->placeUpiOrder();
        $data = $this->withToken($ctx['token'])
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $ctx['orderId'],
                'method' => 'upi',
                'mode' => 'upi_intent',
            ])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame('upi_intent', data_get($data, 'client_payload.upi_mode'));
        $this->assertNotEmpty(data_get($data, 'client_payload.upi_intent_url'));
    }

    public function test_cod_unaffected_by_upi(): void
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
            'name' => 'QA21 COD',
            'phone' => '9333333333',
            'line1' => 'COD',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'COD-QA21',
            'name' => 'Standard',
            'price' => 20,
            'status' => 'active',
            'eta_min_days' => 1,
            'eta_max_days' => 3,
        ]);
        $this->withToken($token)->postJson('/api/v1/checkout/preview', [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
        ])->assertOk();
        $order = $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa21_cod_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $shipping->id,
                'payment_method' => 'cod',
            ])
            ->assertSuccessful()
            ->json('data');
        $orderId = (int) ($order['id'] ?? $order['order']['id'] ?? 0);
        $this->assertSame('CONFIRMED', Order::query()->find($orderId)?->status);
    }
}
