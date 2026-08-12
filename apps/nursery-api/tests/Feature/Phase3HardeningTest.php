<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Services\OrderStateMachine;
use App\Modules\Order\Models\Order;
use App\Shared\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase3HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        foreach (['customer', 'admin', 'super_admin'] as $slug) {
            Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
        }

        foreach (['products.read', 'orders.view', 'reports.view', 'users.manage'] as $slug) {
            Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug],
            );
        }

        $admin = Role::query()->where('slug', 'admin')->first();
        $admin?->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['products.read', 'orders.view', 'reports.view'])->pluck('id')
        );
    }

    private function makeUser(string $email, array $roles, string $status = 'active'): User
    {
        $user = User::query()->create([
            'name' => 'Test '.$email,
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => $status])->save();
        $roleIds = Role::query()->whereIn('slug', $roles)->pluck('id')->all();
        $user->roles()->sync($roleIds);

        return $user->fresh(['roles']);
    }

    public function test_health_endpoint_reports_database(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.database', 'healthy');
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $this->seedRoles();
        $customer = $this->makeUser('cust@example.com', ['customer']);
        $token = JWTAuth::fromUser($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_admin_returns_401(): void
    {
        $this->getJson('/api/v1/admin/products')
            ->assertStatus(401);
    }

    public function test_inactive_admin_is_rejected_by_active_user_middleware(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser('blocked-admin@example.com', ['admin'], 'blocked');
        $token = JWTAuth::fromUser($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/products')
            ->assertStatus(403);
    }

    public function test_admin_products_per_page_is_capped(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser('admin@example.com', ['admin']);
        $token = JWTAuth::fromUser($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/products?per_page=999999');

        $response->assertOk();
        $this->assertLessThanOrEqual(100, (int) data_get($response->json(), 'meta.pagination.per_page'));
    }

    public function test_order_state_machine_rejects_invalid_transition(): void
    {
        $order = new Order(['status' => 'DELIVERED']);
        $order->id = 1;
        // Persist minimal row for transition save if needed — use in-memory via factory-like create.
        $this->seedRoles();

        $warehouse = Warehouse::query()->create([
            'code' => 'MAIN',
            'name' => 'Main',
            'is_default' => true,
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Test Plant',
            'slug' => 'test-plant-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => 100,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);

        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 5,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        $user = $this->makeUser('buyer@example.com', ['customer']);
        $order = Order::query()->create([
            'order_number' => 'ORD-TEST-1',
            'user_id' => $user->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'warehouse_id' => $warehouse->id,
            'shipping_address_json' => ['line1' => 'Test'],
            'billing_address_json' => ['line1' => 'Test'],
        ]);

        $this->expectException(ApiException::class);
        app(OrderStateMachine::class)->transition($order, 'PENDING_PAYMENT', $user->id);
    }

    public function test_inventory_adjust_rejects_negative_on_hand(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser('inv-admin@example.com', ['admin']);
        // grant inventory.adjust
        $perm = Permission::query()->firstOrCreate(['slug' => 'inventory.adjust'], ['name' => 'inventory.adjust']);
        Role::query()->where('slug', 'admin')->first()?->permissions()->syncWithoutDetaching([$perm->id]);
        $admin = $admin->fresh(['roles.permissions']);
        $token = JWTAuth::fromUser($admin);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH1',
            'name' => 'WH1',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Stock Plant',
            'slug' => 'stock-plant-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => 50,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 2,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'adjustment' => -5,
                'reason' => 'correction',
            ])
            ->assertStatus(409);
    }
}
