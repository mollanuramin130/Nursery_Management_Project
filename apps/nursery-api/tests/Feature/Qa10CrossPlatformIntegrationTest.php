<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-10 — Cross-platform integration: same API state for customer + admin.
 *
 * Note: PHPUnit + tymon/jwt-auth caches the parsed token/user across consecutive
 * $this->getJson/postJson calls. Cross-principal assertions use actingAs('api')
 * so the authenticated subject is explicit (mirrors live multi-client traffic where
 * each client has its own process/session).
 */
class Qa10CrossPlatformIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(string $email = 'qa10-cust@example.com'): User
    {
        $user = User::query()->create([
            'name' => 'QA10 Customer',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    /** Ops staff with explicit permission sync (matches QA-05/QA-09 pattern). */
    private function staff(array $perms, string $email = 'qa10-admin@example.com'): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'qa10_ops'],
            ['name' => 'QA10 Ops'],
        );
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );

        $user = User::query()->create([
            'name' => 'QA10 Admin',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function asUser(User $user, ?string $requestId = null): self
    {
        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();
        $this->actingAs($user, 'api');
        if ($requestId !== null) {
            $this->withHeader('X-Request-Id', $requestId);
        }

        return $this;
    }

    private function productWithStock(float $price = 199, int $qty = 10): Product
    {
        $product = Product::query()->create([
            'name' => 'QA10 Plant',
            'slug' => 'qa10-plant-'.uniqid(),
            'sku' => 'QA10-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => $price,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA10'],
            ['name' => 'QA10 WH', 'status' => 'active'],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        return $product;
    }

    /**
     * @return array{customer: User, admin: User, product: Product, address: Address, shipping: ShippingMethod}
     */
    private function bootstrap(): array
    {
        $customer = $this->customer();
        $admin = $this->staff([
            'orders.view',
            'orders.update_status',
            'orders.cancel',
            'fulfillment.view',
            'fulfillment.manage',
            'returns.view',
        ]);
        $product = $this->productWithStock(199, 15);
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'QA10',
            'phone' => '9876500010',
            'line1' => '10 Cross St',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA10'],
            [
                'name' => 'Standard',
                'base_rate' => 49,
                'estimated_days_min' => 2,
                'estimated_days_max' => 5,
                'status' => 'active',
            ],
        );

        return [
            'customer' => $customer,
            'admin' => $admin,
            'product' => $product,
            'address' => $address,
            'shipping' => $shipping,
        ];
    }

    public function test_same_cod_order_visible_to_customer_and_admin_with_matching_totals(): void
    {
        $ctx = $this->bootstrap();
        $this->assertContains('orders.view', $ctx['admin']->permissionSlugs());

        $this->asUser($ctx['customer'], 'qa10_place_'.uniqid())
            ->postJson('/api/v1/cart/items', [
                'product_id' => $ctx['product']->id,
                'quantity' => 2,
            ])
            ->assertCreated();

        $placed = $this->asUser($ctx['customer'], 'qa10_place_'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $customerShow = $this->asUser($ctx['customer'])
            ->getJson('/api/v1/orders/'.$placed['id'])
            ->assertOk()
            ->json('data');

        $adminShow = $this->asUser($ctx['admin'])
            ->getJson('/api/v1/admin/orders/'.$placed['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame($placed['order_number'], $customerShow['order_number']);
        $this->assertSame($placed['order_number'], $adminShow['order_number']);
        $this->assertSame($customerShow['status'], $adminShow['status']);
        $this->assertEquals((float) $customerShow['grand_total'], (float) $adminShow['grand_total']);
        $this->assertEquals((float) $customerShow['subtotal'], (float) $adminShow['subtotal']);
        $this->assertCount(count($customerShow['items'] ?? []), $adminShow['items'] ?? []);
    }

    public function test_admin_status_transition_propagates_to_customer_order(): void
    {
        $ctx = $this->bootstrap();

        $this->asUser($ctx['customer'], 'qa10_status_cart')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $ctx['product']->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $order = $this->asUser($ctx['customer'], 'qa10_status_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($ctx['admin'])
            ->postJson('/api/v1/admin/orders/'.$order['id'].'/status', [
                'status' => 'PROCESSING',
                'note' => 'QA-10 cross-platform',
            ])
            ->assertOk();

        $customer = $this->asUser($ctx['customer'])
            ->getJson('/api/v1/orders/'.$order['id'])
            ->assertOk()
            ->json('data');
        $admin = $this->asUser($ctx['admin'])
            ->getJson('/api/v1/admin/orders/'.$order['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('PROCESSING', $customer['status']);
        $this->assertSame('PROCESSING', $admin['status']);
    }

    public function test_shipped_creates_lowercase_shipment_status_while_order_is_upper(): void
    {
        $ctx = $this->bootstrap();

        $this->asUser($ctx['customer'], 'qa10_ship_cart')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $ctx['product']->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $order = $this->asUser($ctx['customer'], 'qa10_ship_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        foreach (['PROCESSING', 'PACKED', 'SHIPPED'] as $status) {
            $this->asUser($ctx['admin'])
                ->postJson('/api/v1/admin/orders/'.$order['id'].'/status', [
                    'status' => $status,
                ])
                ->assertOk();
        }

        $admin = $this->asUser($ctx['admin'])
            ->getJson('/api/v1/admin/orders/'.$order['id'])
            ->assertOk()
            ->json('data');
        $customer = $this->asUser($ctx['customer'])
            ->getJson('/api/v1/orders/'.$order['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('SHIPPED', $admin['status']);
        $this->assertSame('SHIPPED', $customer['status']);
        $this->assertSame('shipped', strtolower((string) ($admin['shipment']['status'] ?? '')));
    }

    public function test_idempotent_place_order_and_customer_cannot_hit_admin(): void
    {
        $ctx = $this->bootstrap();
        $rid = 'qa10_idem_'.uniqid();

        $this->asUser($ctx['customer'], $rid)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $ctx['product']->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $first = $this->asUser($ctx['customer'], $rid)
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $second = $this->asUser($ctx['customer'], $rid)
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, Order::query()->where('user_id', $ctx['customer']->id)->count());

        $this->asUser($ctx['customer'])
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(403);
    }

    public function test_cancel_propagates_and_invalid_transition_is_409(): void
    {
        $ctx = $this->bootstrap();

        $this->asUser($ctx['customer'], 'qa10_cancel_cart')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $ctx['product']->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $order = $this->asUser($ctx['customer'], 'qa10_cancel_1')
            ->postJson('/api/v1/orders', [
                'address_id' => $ctx['address']->id,
                'shipping_method_id' => $ctx['shipping']->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($ctx['admin'])
            ->postJson('/api/v1/admin/orders/'.$order['id'].'/status', [
                'status' => 'DELIVERED',
            ])
            ->assertStatus(409);

        $this->asUser($ctx['admin'])
            ->postJson('/api/v1/admin/orders/'.$order['id'].'/status', [
                'status' => 'CANCELLED',
                'note' => 'QA-10 cancel',
            ])
            ->assertOk();

        $customer = $this->asUser($ctx['customer'])
            ->getJson('/api/v1/orders/'.$order['id'])
            ->assertOk()
            ->json('data');
        $this->assertSame('CANCELLED', $customer['status']);
    }
}
