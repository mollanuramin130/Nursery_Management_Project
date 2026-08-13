<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\CheckoutService;
use App\Modules\Promotion\Models\Coupon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-12 — Performance / reliability regression guards.
 */
class Qa12PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(string $email = 'qa12@example.com'): User
    {
        $user = User::query()->create([
            'name' => 'QA12',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function productWithStock(string $sku, int $qty = 10): Product
    {
        $product = Product::query()->create([
            'name' => 'QA12 '.$sku,
            'slug' => 'qa12-'.strtolower($sku).'-'.uniqid(),
            'sku' => $sku.'-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 100,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA12'],
            ['name' => 'QA12 WH', 'status' => 'active'],
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

    public function test_sellable_qty_map_batches_inventory_lookups(): void
    {
        $a = $this->productWithStock('A', 5);
        $b = $this->productWithStock('B', 7);
        $svc = app(InventoryService::class);

        DB::enableQueryLog();
        $map = $svc->sellableQtyMap([
            ['product_id' => $a->id, 'variant_id' => null],
            ['product_id' => $b->id, 'variant_id' => null],
        ]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(5, $map[$svc->sellableKey($a->id, null)]);
        $this->assertSame(7, $map[$svc->sellableKey($b->id, null)]);
        // One inventory query for both products (not N queries).
        $this->assertLessThanOrEqual(2, $queries);
    }

    public function test_inventory_list_paginates_in_sql_not_full_table_php(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->productWithStock('L'.$i, 3);
        }
        $svc = app(InventoryService::class);
        $page1 = $svc->list(null, null, null, null, 2, 1);
        $page2 = $svc->list(null, null, null, null, 2, 2);

        $this->assertCount(2, $page1['data']);
        $this->assertSame(2, $page1['pagination']['per_page']);
        $this->assertGreaterThanOrEqual(5, $page1['pagination']['total']);
        $this->assertNotEquals(
            collect($page1['data'])->pluck('id')->all(),
            collect($page2['data'])->pluck('id')->all(),
        );
    }

    public function test_can_reorder_uses_items_count_without_extra_exists_query(): void
    {
        $user = $this->customer();
        $product = $this->productWithStock('RO', 2);
        $order = Order::query()->create([
            'order_number' => 'QA12-RO-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'cod',
            'shipping_address_json' => ['name' => 'A'],
            'billing_address_json' => ['name' => 'A'],
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        $loaded = Order::query()->withCount('items')->findOrFail($order->id);
        $svc = app(CheckoutService::class);
        $this->assertTrue($svc->canReorder($loaded));
    }

    public function test_admin_coupons_are_paginated_with_meta(): void
    {
        $role = Role::query()->where('slug', 'admin')->first();
        $admin = User::query()->create([
            'name' => 'QA12 Admin',
            'email' => 'qa12-admin@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->forceFill(['status' => 'active'])->save();
        $admin->roles()->sync([$role->id]);

        for ($i = 0; $i < 3; $i++) {
            Coupon::query()->create([
                'code' => 'QA12C'.$i.uniqid(),
                'name' => 'Coupon '.$i,
                'discount_type' => 'percent',
                'discount_value' => 10,
                'status' => 'active',
                'is_public' => true,
                'stackable' => false,
            ]);
        }

        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/coupons?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_expired_reservation_command_transitions_empty_item_orders(): void
    {
        $user = $this->customer('qa12-exp@example.com');
        $order = Order::query()->create([
            'order_number' => 'QA12-EXP-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'INR',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
            'payment_method' => 'razorpay',
            'shipping_address_json' => ['name' => 'A'],
            'billing_address_json' => ['name' => 'A'],
            'placed_at' => now()->subDays(2),
        ]);
        Order::query()->whereKey($order->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('inventory:release-expired-reservations', ['--hours' => 1])
            ->assertSuccessful();

        $this->assertSame('PAYMENT_FAILED', $order->fresh()->status);
    }
}
