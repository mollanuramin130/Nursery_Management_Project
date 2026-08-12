<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Supplier;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Shared\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase6InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function grantInventoryAdmin(string $email = 'ops@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['inventory.view', 'inventory.adjust'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['inventory.view', 'inventory.adjust'])->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    private function viewOnlyAdmin(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Permission::query()->firstOrCreate(['slug' => 'inventory.view'], ['name' => 'inventory.view']);
        $user = User::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer-inv@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->sync(
            Permission::query()->where('slug', 'inventory.view')->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    /**
     * @return array{warehouse: Warehouse, product: Product, item: InventoryItem}
     */
    private function seedStock(int $onHand = 10, int $reserved = 0): array
    {
        $warehouse = Warehouse::query()->create([
            'code' => 'MAIN',
            'name' => 'Main Nursery',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Snake Plant',
            'slug' => 'snake-'.uniqid(),
            'sku' => 'SP-'.uniqid(),
            'price' => 499,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);
        $item = InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => $onHand,
            'qty_reserved' => $reserved,
            'qty_damaged' => 0,
            'low_stock_threshold' => 5,
        ]);

        return compact('warehouse', 'product', 'item');
    }

    public function test_reserve_release_and_commit_preserve_available_math(): void
    {
        $seed = $this->seedStock(10, 0);
        $svc = app(InventoryService::class);
        $lines = [['product_id' => $seed['product']->id, 'quantity' => 6]];

        $svc->reserve($lines, 'order', 1);
        $seed['item']->refresh();
        $this->assertSame(10, (int) $seed['item']->qty_on_hand);
        $this->assertSame(6, (int) $seed['item']->qty_reserved);
        $this->assertSame(4, $svc->sellable($seed['item']));

        $svc->release([['product_id' => $seed['product']->id, 'quantity' => 2]], 'order', 1);
        $seed['item']->refresh();
        $this->assertSame(4, (int) $seed['item']->qty_reserved);
        $this->assertSame(6, $svc->sellable($seed['item']));

        $svc->commit([['product_id' => $seed['product']->id, 'quantity' => 4]], 'order', 1);
        $seed['item']->refresh();
        $this->assertSame(6, (int) $seed['item']->qty_on_hand);
        $this->assertSame(0, (int) $seed['item']->qty_reserved);
        $this->assertSame(6, $svc->sellable($seed['item']));
    }

    public function test_concurrent_reserve_cannot_oversell(): void
    {
        $seed = $this->seedStock(10, 0);
        $svc = app(InventoryService::class);
        $ok = 0;
        $fail = 0;

        try {
            $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 7]], 'order', 101);
            $ok++;
        } catch (ApiException) {
            $fail++;
        }

        try {
            $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 5]], 'order', 102);
            $ok++;
        } catch (ApiException) {
            $fail++;
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $seed['item']->refresh();
        $this->assertLessThanOrEqual(10, (int) $seed['item']->qty_reserved);
        $this->assertGreaterThanOrEqual(0, $svc->sellable($seed['item']));
    }

    public function test_partial_and_full_purchase_receiving(): void
    {
        $admin = $this->grantInventoryAdmin();
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedStock(0, 0);

        $supplier = Supplier::query()->create([
            'code' => 'SUP1',
            'name' => 'Green Growers',
            'status' => 'active',
        ]);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/purchase-orders', [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $seed['warehouse']->id,
                'status' => 'approved',
                'items' => [
                    [
                        'product_id' => $seed['product']->id,
                        'quantity' => 100,
                        'unit_cost' => 50,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $poId = $create['id'];
        $itemId = $create['items'][0]['id'];

        $partial = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
                'items' => [
                    ['purchase_order_item_id' => $itemId, 'quantity' => 60, 'damaged' => 0],
                ],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('partially_received', $partial['status']);
        $this->assertSame(60, $partial['items'][0]['quantity_received']);
        $this->assertSame(40, $partial['items'][0]['quantity_remaining']);

        $seed['item']->refresh();
        $this->assertSame(60, (int) $seed['item']->qty_on_hand);

        $full = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
                'items' => [
                    ['purchase_order_item_id' => $itemId, 'quantity' => 40, 'damaged' => 0],
                ],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('received', $full['status']);
        $this->assertSame(100, $full['items'][0]['quantity_received']);
        $seed['item']->refresh();
        $this->assertSame(100, (int) $seed['item']->qty_on_hand);
        $this->assertTrue(
            StockMovement::query()->where('type', 'purchase_in')->where('product_id', $seed['product']->id)->exists()
        );
    }

    public function test_damaged_receipt_increases_damaged_not_sellable(): void
    {
        $admin = $this->grantInventoryAdmin('dmg@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedStock(0, 0);
        $supplier = Supplier::query()->create(['code' => 'SUP2', 'name' => 'S2', 'status' => 'active']);

        $po = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/purchase-orders', [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $seed['warehouse']->id,
                'status' => 'approved',
                'items' => [['product_id' => $seed['product']->id, 'quantity' => 100, 'unit_cost' => 10]],
            ])
            ->assertCreated()
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/admin/purchase-orders/{$po['id']}/receive", [
                'items' => [[
                    'purchase_order_item_id' => $po['items'][0]['id'],
                    'quantity' => 100,
                    'damaged' => 5,
                ]],
            ])
            ->assertOk();

        $seed['item']->refresh();
        $this->assertSame(100, (int) $seed['item']->qty_on_hand);
        $this->assertSame(5, (int) $seed['item']->qty_damaged);
        $this->assertSame(95, app(InventoryService::class)->sellable($seed['item']));
    }

    public function test_reconcile_creates_adjust_out_movement(): void
    {
        $admin = $this->grantInventoryAdmin('rec@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedStock(100, 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/reconcile', [
                'inventory_item_id' => $seed['item']->id,
                'physical_qty' => 97,
                'reason' => 'Cycle count variance',
            ])
            ->assertOk()
            ->assertJsonPath('data.difference', -3)
            ->assertJsonPath('data.adjusted', true);

        $seed['item']->refresh();
        $this->assertSame(97, (int) $seed['item']->qty_on_hand);
        $this->assertTrue(
            StockMovement::query()
                ->where('inventory_item_id', $seed['item']->id)
                ->where('type', 'adjust_out')
                ->where('qty_delta', -3)
                ->exists()
        );
    }

    public function test_adjust_requires_permission(): void
    {
        $viewer = $this->viewOnlyAdmin();
        $token = JWTAuth::fromUser($viewer);
        $seed = $this->seedStock(5, 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', [
                'warehouse_id' => $seed['warehouse']->id,
                'product_id' => $seed['product']->id,
                'adjustment' => 1,
                'reason' => 'correction',
            ])
            ->assertStatus(403);
    }

    public function test_warehouse_crud_and_reorder_suggestions(): void
    {
        $admin = $this->grantInventoryAdmin('wh@example.com');
        $token = JWTAuth::fromUser($admin);
        $this->seedStock(2, 0); // low stock vs threshold 5

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/warehouses', [
                'code' => 'NORTH',
                'name' => 'North Yard',
                'city' => 'Pune',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NORTH');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/warehouses')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/inventory/reorder-suggestions')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_adjust_idempotency_via_reference(): void
    {
        $admin = $this->grantInventoryAdmin('idem@example.com');
        $token = JWTAuth::fromUser($admin);
        $seed = $this->seedStock(10, 0);

        $payload = [
            'warehouse_id' => $seed['warehouse']->id,
            'product_id' => $seed['product']->id,
            'adjustment' => 5,
            'reason' => 'purchase_in',
            'reference_type' => 'test_ref',
            'reference_id' => 999,
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $seed['item']->refresh();
        $this->assertSame(15, (int) $seed['item']->qty_on_hand);
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'test_ref')->where('reference_id', 999)->count());
    }
}
