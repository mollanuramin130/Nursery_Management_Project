<?php

namespace Tests\Feature;

use Database\Seeders\NuvikozCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class NuvikozCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_rolls_back_every_catalog_table(): void
    {
        $result = app(NuvikozCatalogSeeder::class)->import(true);
        $this->assertSame(30, $result['created']);
        foreach (['products', 'categories', 'warehouses', 'inventory_items', 'plant_profiles', 'product_images', 'product_categories'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_import_is_repeatable_and_preserves_admin_changes(): void
    {
        $seeder = app(NuvikozCatalogSeeder::class);
        $this->assertSame(30, $seeder->import()['created']);
        $id = DB::table('products')->where('sku', 'NVK-PL-001')->value('id');
        DB::table('products')->where('id', $id)->update(['price' => 275, 'slug' => 'my-money-plant', 'status' => 'draft']);
        DB::table('inventory_items')->where('product_id', $id)->update(['qty_on_hand' => 7]);
        $result = $seeder->import();
        $this->assertSame(0, $result['created']);
        $this->assertSame(30, $result['skipped']);
        $this->assertDatabaseHas('products', ['id' => $id, 'price' => 275, 'status' => 'draft', 'slug' => 'my-money-plant']);
        $this->assertDatabaseHas('inventory_items', ['product_id' => $id, 'qty_on_hand' => 7]);
        $this->assertDatabaseCount('products', 30);
        $this->assertDatabaseCount('categories', 7);
        $this->assertDatabaseCount('plant_profiles', 30);
        $this->assertDatabaseCount('product_images', 30);
        $this->assertSame(0, (int) DB::table('products')->sum('rating_count'));
        $this->assertSame(0.0, (float) DB::table('products')->sum('rating_avg'));
        $this->assertSame(30, DB::table('products')->where('stock_status', 'out_of_stock')->count());
    }

    public function test_soft_deleted_products_are_not_resurrected(): void
    {
        $seeder = app(NuvikozCatalogSeeder::class);
        $seeder->import();
        DB::table('products')->where('sku', 'NVK-PL-001')->update(['deleted_at' => now()]);
        $this->assertSame(30, $seeder->import()['skipped']);
        $this->assertNotNull(DB::table('products')->where('sku', 'NVK-PL-001')->value('deleted_at'));
    }

    public function test_collision_rolls_back_all_new_rows(): void
    {
        DB::table('products')->insert([
            'sku' => 'OWNER-PLANT', 'slug' => 'snake-plant', 'name' => 'Existing plant',
            'product_type' => 'plant', 'price' => 100,
        ]);
        try {
            app(NuvikozCatalogSeeder::class)->import();
            $this->fail('Expected a conflicting slug to abort the import.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('collision', $e->getMessage());
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('inventory_items', 0);
    }
}
