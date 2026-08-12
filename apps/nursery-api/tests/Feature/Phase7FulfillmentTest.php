<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\Shipment;
use App\Modules\Order\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase7FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms, string $email = 'fulfill@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Fulfillment Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    /**
     * @return array{order: Order, warehouse: Warehouse, product: Product, customer: User}
     */
    private function seedConfirmedOrder(int $qty = 5): array
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer = User::query()->create([
            'name' => 'Buyer',
            'email' => 'buyer-f7@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $customer->forceFill(['status' => 'active'])->save();
        $customer->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $warehouse = Warehouse::query()->create([
            'code' => 'MAIN',
            'name' => 'Main',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Snake Plant',
            'slug' => 'snake-f7-'.uniqid(),
            'sku' => 'SP-F7-'.uniqid(),
            'price' => 100,
            'currency' => 'INR',
            'status' => 'active',
        ]);
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 50,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 5,
        ]);

        $addr = [
            'name' => 'Buyer', 'phone' => '9999999999', 'line1' => 'Street 1',
            'city' => 'Pune', 'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
        ];
        $order = Order::query()->create([
            'order_number' => 'GL-F7-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 100 * $qty,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100 * $qty,
            'payment_method' => 'cod',
            'warehouse_id' => $warehouse->id,
            'shipping_address_json' => $addr,
            'billing_address_json' => $addr,
            'confirmed_at' => now(),
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 100,
            'quantity' => $qty,
            'line_total' => 100 * $qty,
        ]);

        return compact('order', 'warehouse', 'product', 'customer');
    }

    public function test_normal_fulfillment_flow_pick_pack_ship_deliver(): void
    {
        $admin = $this->staff([
            'fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship', 'fulfillment.manage',
        ]);
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedConfirmedOrder(5);
        $id = $seed['order']->id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'PROCESSING');

        $itemId = $seed['order']->items()->first()->id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick", [
                'items' => [['order_item_id' => $itemId, 'picked' => 5]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.picked', 5);

        $over = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick", [
                'items' => [['order_item_id' => $itemId, 'picked' => 6]],
            ]);
        $this->assertTrue(in_array($over->status(), [409, 422], true), 'Over-pick must be rejected');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/complete")
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pack", ['package_count' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'PACKED');

        $ship1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", [])
            ->assertCreated()
            ->json('data');

        $ship2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", [])
            ->assertOk()
            ->json('data');

        $this->assertSame($ship1['tracking_number'], $ship2['tracking_number']);
        $this->assertSame(1, Shipment::query()->where('order_id', $id)->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/out-for-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERED');
    }

    public function test_delivery_failure_and_retry(): void
    {
        $admin = $this->staff([
            'fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship',
        ], 'fail@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedConfirmedOrder(2);
        $id = $seed['order']->id;
        $itemId = $seed['order']->items()->first()->id;

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start");
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick", [
            'items' => [['order_item_id' => $itemId, 'picked' => 2]],
        ]);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/complete");
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pack", []);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", []);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/out-for-delivery");

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/fail-delivery", [
                'reason' => 'CUSTOMER_UNAVAILABLE',
                'note' => 'No answer',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERY_FAILED');

        $this->assertNotSame('CANCELLED', Order::query()->find($id)?->status);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/retry-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');
    }

    public function test_cancel_before_ship_still_allowed_via_state_machine(): void
    {
        $seed = $this->seedConfirmedOrder(1);
        app(OrderStateMachine::class)->transition($seed['order'], 'CANCELLED', null, 'Customer cancel');
        $this->assertSame('CANCELLED', $seed['order']->fresh()->status);
    }

    public function test_cancel_after_ship_rejected(): void
    {
        $seed = $this->seedConfirmedOrder(1);
        $order = $seed['order'];
        foreach (['PROCESSING', 'PACKED', 'SHIPPED'] as $to) {
            app(OrderStateMachine::class)->transition($order->fresh(), $to);
        }

        $this->expectException(\App\Shared\Exceptions\ApiException::class);
        app(OrderStateMachine::class)->transition($order->fresh(), 'CANCELLED');
    }

    public function test_customer_cannot_access_admin_fulfillment(): void
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer = User::query()->create([
            'name' => 'C',
            'email' => 'cust-f7@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $customer->forceFill(['status' => 'active'])->save();
        $customer->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));
        $token = JWTAuth::fromUser($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/fulfillment')
            ->assertStatus(403);
    }

    public function test_ship_requires_fulfillment_ship_permission(): void
    {
        $viewer = $this->staff(['fulfillment.view'], 'view-only-f7@example.com');
        $token = JWTAuth::fromUser($viewer);
        $seed = $this->seedConfirmedOrder(1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$seed['order']->id}/ship", [])
            ->assertStatus(403);
    }

    public function test_concurrent_start_picking_is_idempotent(): void
    {
        $admin = $this->staff(['fulfillment.view', 'fulfillment.pick'], 'pick2@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedConfirmedOrder(1);
        $id = $seed['order']->id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'PROCESSING');
    }

    public function test_customer_tracking_excludes_internal_exception_notes(): void
    {
        $admin = $this->staff([
            'fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship',
        ], 'track@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedConfirmedOrder(1);
        $id = $seed['order']->id;
        $itemId = $seed['order']->items()->first()->id;

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start");
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick", [
            'items' => [['order_item_id' => $itemId, 'picked' => 1]],
        ]);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/complete");
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/pack", []);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", [
            'carrier' => 'GreenLeaf Delivery',
        ]);

        $customer = $seed['customer']->fresh();
        $tracking = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/orders/{$id}/tracking")
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('timeline', $tracking);
        $this->assertArrayHasKey('shipment', $tracking);
        $this->assertArrayNotHasKey('exceptions', $tracking);
        $this->assertArrayNotHasKey('fulfillment', $tracking);
        $this->assertNotEmpty($tracking['shipment']['tracking_number'] ?? null);
    }
}
