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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase20FulfillmentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms, string $email = 'p20@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::query()->firstOrCreate(['slug' => 'delivery_manager'], ['name' => 'Delivery Manager']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Phase20 Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    private function seedConfirmedOrder(string $sku = 'P20-SKU-1'): array
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer = User::query()->create([
            'name' => 'Buyer P20',
            'email' => 'buyer-p20-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $customer->forceFill(['status' => 'active'])->save();

        $warehouse = Warehouse::query()->create([
            'code' => 'P20',
            'name' => 'P20 WH',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'P20 Plant',
            'slug' => 'p20-'.uniqid(),
            'sku' => $sku,
            'price' => 50,
            'currency' => 'INR',
            'status' => 'active',
        ]);
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 20,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        $addr = [
            'name' => 'Buyer', 'phone' => '9000000000', 'line1' => 'Lane 1',
            'city' => 'Pune', 'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
        ];
        $order = Order::query()->create([
            'order_number' => 'GL-P20-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
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
            'unit_price' => 50,
            'quantity' => 2,
            'line_total' => 100,
        ]);

        return compact('order', 'warehouse', 'product', 'customer');
    }

    private function advanceToShipped(string $token, int $id, string $sku): void
    {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/scan", [
                'code' => $sku,
                'increment_by' => 2,
            ])->assertOk()->assertJsonPath('data.scan.matched', true);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/complete")->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pack", ['package_count' => 1])->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/ship", [])->assertCreated();
    }

    public function test_pick_scan_rejects_wrong_sku(): void
    {
        $admin = $this->staff(['fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship']);
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedConfirmedOrder('GOOD-SKU');
        $id = $seed['order']->id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/start")->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/pick/scan", ['code' => 'WRONG-SKU'])
            ->assertStatus(422);
    }

    public function test_assign_driver_pod_and_reschedule(): void
    {
        $admin = $this->staff(['fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship']);
        $token = JWTAuth::fromUser($admin);
        $sku = 'P20-DRV-'.uniqid();
        $seed = $this->seedConfirmedOrder($sku);
        $id = $seed['order']->id;

        $driver = User::query()->create([
            'name' => 'Driver One',
            'email' => 'driver-p20-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $driver->forceFill(['status' => 'active'])->save();
        $driver->roles()->sync(Role::query()->where('slug', 'delivery_manager')->pluck('id'));

        $this->advanceToShipped($token, $id, $sku);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/assign-driver", [
                'driver_user_id' => $driver->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.assigned_driver.id', $driver->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/reschedule", [
                'eta_date' => now()->addDays(2)->format('Y-m-d'),
                'note' => 'Customer requested later slot',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.meta.reschedule.note', 'Customer requested later slot');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/out-for-delivery")->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/fulfillment/orders/{$id}/deliver", [
                'method' => 'note',
                'note' => 'Left with guard',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERED')
            ->assertJsonPath('data.shipment.meta.pod.note', 'Left with guard');
    }

    public function test_drivers_list(): void
    {
        $admin = $this->staff(['fulfillment.view']);
        $token = JWTAuth::fromUser($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/fulfillment/drivers')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
