<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationChannelsJob;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\UserDevice;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\OrderNotificationDispatcher;
use App\Modules\Notification\Support\NotificationChannelMap;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\FulfillmentService;
use App\Integrations\Push\FcmPushGateway;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Qa22NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(?string $email = null): User
    {
        $user = User::query()->create([
            'name' => 'QA22 Customer',
            'email' => $email ?? ('qa22-cust-'.uniqid().'@example.com'),
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function staffAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'QA22 Admin',
            'email' => 'qa22-admin-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'admin')->pluck('id'));

        return $user->fresh();
    }

    private function product(): Product
    {
        $product = Product::query()->create([
            'name' => 'QA22 Plant',
            'slug' => 'qa22-plant-'.uniqid(),
            'sku' => 'QA22-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 500,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA22'],
            ['name' => 'QA22 WH', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 50,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    private function shipping(): ShippingMethod
    {
        return ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA22'],
            ['name' => 'Standard', 'status' => 'active', 'base_rate' => 50],
        );
    }

    private function minimalOrder(User $customer, string $status, string $number): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => $number,
            'status' => $status,
            'payment_method' => 'cod',
            'subtotal' => 500,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 50,
            'grand_total' => 550,
            'currency' => 'INR',
            'shipping_address_json' => [
                'name' => 'Asha',
                'phone' => '9999999999',
                'line1' => '1 Garden',
                'city' => 'Pune',
                'state' => 'MH',
                'postal_code' => '411001',
                'country' => 'IN',
            ],
            'placed_at' => now(),
        ]);
    }

    public function test_channel_map_includes_qa22_types(): void
    {
        foreach (['new_order', 'payment_confirmed', 'order_processing', 'order_cancelled', 'return_approved', 'refund_completed'] as $type) {
            $cfg = NotificationChannelMap::forType($type);
            $this->assertContains('push', $cfg['channels'], $type);
            $this->assertContains('in_app', $cfg['channels'], $type);
        }
    }

    public function test_device_register_with_push_token_and_deactivate(): void
    {
        $user = $this->customer();
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/devices/register', [
                'platform' => 'android',
                'device_id' => 'dev-qa22-1',
                'push_token' => 'fcm-token-qa22-abc',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'push_token' => 'fcm-token-qa22-abc',
            'is_active' => 1,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/devices/deactivate', [
                'device_id' => 'dev-qa22-1',
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'dev-qa22-1',
            'is_active' => 0,
        ]);
    }

    public function test_customer_cannot_access_another_users_notification(): void
    {
        $a = $this->customer('qa22-a@example.com');
        $b = $this->customer('qa22-b@example.com');
        $svc = app(NotificationService::class);
        $n = $svc->notify($a, 'order_packed', 'Packed', 'Body', [
            'order_id' => 99,
            'route' => '/account/orders/99',
        ]);

        $this->withToken(JWTAuth::fromUser($b))
            ->postJson('/api/v1/notifications/'.$n->id.'/read')
            ->assertNotFound();
    }

    public function test_cod_order_notifies_staff_new_order_and_customer_confirmed(): void
    {
        Queue::fake();
        $admin = $this->staffAdmin();
        $customer = $this->customer();
        $product = $this->product();
        $ship = $this->shipping();
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA22',
            'phone' => '9444444444',
            'line1' => '22 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);

        $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa22-cod-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated();

        $this->assertTrue(
            Notification::query()->where('user_id', $admin->id)->where('type', 'new_order')->exists()
        );
        $this->assertTrue(
            Notification::query()->where('user_id', $customer->id)->where('type', 'order_confirmed')->exists()
        );

        $staffNote = Notification::query()->where('user_id', $admin->id)->where('type', 'new_order')->first();
        $this->assertSame('admin', data_get($staffNote->data_json, 'audience'));
        $this->assertStringContainsString('/orders/', (string) data_get($staffNote->data_json, 'route'));
    }

    public function test_status_transition_notifies_customer_idempotently(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $order = $this->minimalOrder($customer, 'PROCESSING', 'ORD-QA22-PACK');

        $dispatcher = app(OrderNotificationDispatcher::class);
        $dispatcher->notifyCustomerStatus($order, 'PACKED');
        $dispatcher->notifyCustomerStatus($order, 'PACKED');

        $this->assertSame(
            1,
            Notification::query()->where('user_id', $customer->id)->where('type', 'order_packed')->count()
        );
        Queue::assertPushed(DeliverNotificationChannelsJob::class, 1);
    }

    public function test_payment_confirmed_dispatcher_is_idempotent_per_order(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $this->staffAdmin();
        $order = $this->minimalOrder($customer, 'CONFIRMED', 'ORD-QA22-PAY');
        $order->payment_method = 'upi';
        $order->save();

        $dispatcher = app(OrderNotificationDispatcher::class);
        $dispatcher->notifyPaymentConfirmed($order);
        $dispatcher->notifyPaymentConfirmed($order);

        $this->assertSame(
            1,
            Notification::query()->where('user_id', $customer->id)->where('type', 'payment_confirmed')->count()
        );
    }

    public function test_push_job_runs_with_local_fcm_stub(): void
    {
        $user = $this->customer();
        UserDevice::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'device_id' => 'stub-device',
            'push_token' => 'stub-token',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $svc = app(NotificationService::class);
        $n = $svc->notify($user, 'order_shipped', 'Shipped', 'On the way', [
            'order_id' => 7,
            'order_number' => 'ORD-7',
            'route' => '/account/orders/7',
        ]);

        (new DeliverNotificationChannelsJob($n->id))->handle(
            app(PreferenceService::class),
            app(FcmPushGateway::class),
        );

        $this->assertTrue((bool) UserDevice::query()->where('user_id', $user->id)->value('is_active'));
    }

    public function test_processing_notify_from_fulfillment_start_picking(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $product = $this->product();
        $wh = Warehouse::query()->where('code', 'WH-QA22')->first();
        $order = $this->minimalOrder($customer, 'CONFIRMED', 'ORD-QA22-PROC');
        $order->warehouse_id = $wh?->id;
        $order->save();

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 500,
            'quantity' => 1,
            'line_total' => 500,
        ]);

        app(FulfillmentService::class)->startPicking($order->id, null);

        $this->assertTrue(
            Notification::query()->where('user_id', $customer->id)->where('type', 'order_processing')->exists()
        );
        $this->assertSame('PROCESSING', Order::query()->find($order->id)?->status);
    }

    public function test_deep_link_payload_has_no_secrets(): void
    {
        $customer = $this->customer();
        $order = $this->minimalOrder($customer, 'PACKED', 'ORD-QA22-SEC');
        $data = app(OrderNotificationDispatcher::class)->customerDeepLinkData($order, 'order_packed');
        $encoded = json_encode($data);
        $this->assertStringNotContainsString('secret', strtolower($encoded));
        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertStringNotContainsString('jwt', strtolower($encoded));
        $this->assertArrayHasKey('route', $data);
    }
}
