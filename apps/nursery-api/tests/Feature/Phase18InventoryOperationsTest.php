<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase18InventoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function ops(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['inventory.view', 'inventory.adjust', 'inventory.transfer'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'P18 Ops',
            'email' => 'p18-ops@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['inventory.view', 'inventory.adjust', 'inventory.transfer'])->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    /**
     * @return array{from: Warehouse, to: Warehouse, product: Product, item: InventoryItem}
     */
    private function seedTwoWarehouses(int $onHand = 100): array
    {
        $from = Warehouse::query()->create([
            'code' => 'MAIN-P18',
            'name' => 'Main',
            'is_default' => true,
            'status' => 'active',
        ]);
        $to = Warehouse::query()->create([
            'code' => 'GH-A',
            'name' => 'Greenhouse A',
            'is_default' => false,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Monstera',
            'slug' => 'monstera-p18-'.uniqid(),
            'sku' => 'MON-'.uniqid(),
            'price' => 799,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);
        $item = InventoryItem::query()->create([
            'warehouse_id' => $from->id,
            'product_id' => $product->id,
            'qty_on_hand' => $onHand,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 10,
        ]);

        return compact('from', 'to', 'product', 'item');
    }

    public function test_dashboard_and_idempotent_reserve_commit(): void
    {
        $seed = $this->seedTwoWarehouses(10);
        $svc = app(InventoryService::class);
        $lines = [['product_id' => $seed['product']->id, 'quantity' => 2]];

        $svc->reserve($lines, 'order', 9001);
        $svc->reserve($lines, 'order', 9001); // idempotent replay
        $seed['item']->refresh();
        $this->assertSame(2, (int) $seed['item']->qty_reserved);

        $svc->commit($lines, 'order', 9001);
        $svc->commit($lines, 'order', 9001); // idempotent
        $seed['item']->refresh();
        $this->assertSame(8, (int) $seed['item']->qty_on_hand);
        $this->assertSame(0, (int) $seed['item']->qty_reserved);
        $this->assertSame(1, StockMovement::query()->where('type', 'sale')->where('reference_id', 9001)->count());

        $staff = $this->ops();
        $token = JWTAuth::fromUser($staff);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/inventory/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_skus', 'low_stock_count', 'definitions']]);
    }

    public function test_transfer_complete_is_atomic(): void
    {
        $seed = $this->seedTwoWarehouses(50);
        $staff = $this->ops();
        $token = JWTAuth::fromUser($staff);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/transfers', [
                'from_warehouse_id' => $seed['from']->id,
                'to_warehouse_id' => $seed['to']->id,
                'items' => [['product_id' => $seed['product']->id, 'quantity' => 20]],
            ])
            ->assertCreated();

        $id = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/transfers/'.$id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $from = InventoryItem::query()->where('warehouse_id', $seed['from']->id)->where('product_id', $seed['product']->id)->first();
        $to = InventoryItem::query()->where('warehouse_id', $seed['to']->id)->where('product_id', $seed['product']->id)->first();
        $this->assertSame(30, (int) $from->qty_on_hand);
        $this->assertSame(20, (int) $to->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_out', 'qty_delta' => -20]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_in', 'qty_delta' => 20]);
    }

    public function test_adjustment_records_before_after_and_compensating_correction(): void
    {
        $seed = $this->seedTwoWarehouses(100);
        $staff = $this->ops();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', [
                'warehouse_id' => $seed['from']->id,
                'product_id' => $seed['product']->id,
                'adjustment' => -5,
                'reason' => 'loss',
                'note' => 'Damaged plant write-off',
            ])
            ->assertOk();

        $movement = StockMovement::query()->where('type', 'loss')->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertSame(100, (int) $movement->qty_before);
        $this->assertSame(95, (int) $movement->qty_after);

        // Compensating correction — do not edit history
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/inventory/adjust', [
                'warehouse_id' => $seed['from']->id,
                'product_id' => $seed['product']->id,
                'adjustment' => 5,
                'reason' => 'found',
                'note' => 'Compensating correction',
            ])
            ->assertOk();

        $seed['item']->refresh();
        $this->assertSame(100, (int) $seed['item']->qty_on_hand);
        $this->assertSame(2, StockMovement::query()->where('product_id', $seed['product']->id)->whereIn('type', ['loss', 'adjust_in'])->count());
    }

    public function test_oversell_blocked_under_lock(): void
    {
        $seed = $this->seedTwoWarehouses(1);
        $svc = app(InventoryService::class);
        $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 1]], 'order', 1);

        $this->expectException(\App\Shared\Exceptions\ApiException::class);
        $svc->reserve([['product_id' => $seed['product']->id, 'quantity' => 1]], 'order', 2);
    }
}
