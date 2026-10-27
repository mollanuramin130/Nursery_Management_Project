<?php

namespace Database\Seeders;

use App\Modules\Catalog\Services\HomeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Catalog only: never creates users, orders, reviews, or invented stock. */
class NuvikozCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $result = $this->import();
        $this->command?->info("Created {$result['created']} plants; skipped {$result['skipped']} existing plants.");
    }

    public function import(bool $dryRun = false): array
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/nuvikoz-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $baseUrl = rtrim((string) config('app.url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('APP_URL must be a valid absolute URL before seeding.');
        }
        $categorySlugs = array_column($data['categories'], 'slug');
        $skus = array_column($data['products'], 'sku');
        $slugs = array_column($data['products'], 'slug');
        if (count($skus) !== count(array_unique($skus)) || count($slugs) !== count(array_unique($slugs))) {
            throw new RuntimeException('Duplicate SKU or slug in catalog dataset.');
        }
        foreach ($data['products'] as $plant) {
            if ($plant['price'] <= 0 || array_diff($plant['categories'], $categorySlugs)) {
                throw new RuntimeException('Invalid price or category for '.$plant['sku']);
            }
            if (! preg_match('/^[a-z0-9-]+$/', $plant['slug']) || ! is_file(base_path('public/catalog/starter/'.$plant['slug'].'.svg'))) {
                throw new RuntimeException('Missing or invalid catalog illustration for '.$plant['sku']);
            }
        }

        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $now = now();
            $categories = [];
            foreach ($data['categories'] as $order => $category) {
                $existing = DB::table('categories')->where('slug', $category['slug'])->first();
                if ($existing && ($existing->deleted_at !== null || $existing->status !== 'active')) {
                    throw new RuntimeException('Category '.$category['slug'].' is inactive/deleted. Resolve it in Admin before importing.');
                }
                $categories[$category['slug']] = $existing?->id ?? DB::table('categories')->insertGetId([
                    'name' => $category['name'], 'slug' => $category['slug'],
                    'image_url' => $baseUrl.'/catalog/starter/'.$category['image'].'.svg',
                    'sort_order' => $order, 'status' => 'active',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $warehouse = DB::table('warehouses')->whereNull('deleted_at')->where('status', 'active')
                ->orderByDesc('is_default')->orderBy('id')->first();
            if (! $warehouse && DB::table('warehouses')->where('code', 'NUVIKOZ-MAIN')->exists()) {
                throw new RuntimeException('NUVIKOZ-MAIN warehouse exists but is inactive/deleted. Resolve it before importing.');
            }
            $warehouseId = $warehouse?->id ?? DB::table('warehouses')->insertGetId([
                'code' => 'NUVIKOZ-MAIN', 'name' => 'Nuvikoz Nursery',
                'status' => 'active', 'is_default' => ! DB::table('warehouses')->whereNull('deleted_at')->where('is_default', true)->exists(),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $result = ['created' => 0, 'skipped' => 0, 'dry_run' => $dryRun];
            foreach ($data['products'] as $offset => $plant) {
                // Query builder includes soft-deleted rows: never resurrect or overwrite an Admin edit.
                $matches = DB::table('products')->where('sku', $plant['sku'])->orWhere('slug', $plant['slug'])->get();
                if ($matches->isNotEmpty()) {
                    if ($matches->count() !== 1 || $matches->first()->sku !== $plant['sku']) {
                        throw new RuntimeException('SKU/slug collision for '.$plant['sku'].'; no data was imported.');
                    }
                    $result['skipped']++;
                    continue;
                }
                $id = DB::table('products')->insertGetId([
                    'product_type' => 'plant', 'sku' => $plant['sku'], 'slug' => $plant['slug'], 'name' => $plant['name'],
                    'description' => $plant['description'], 'price' => $plant['price'], 'currency' => 'INR',
                    'status' => 'active', 'stock_status' => 'out_of_stock',
                    'is_featured' => in_array($offset + 1, [1, 2, 5, 11, 12, 17, 19, 21, 26, 27], true),
                    'is_new' => true, 'rating_avg' => 0, 'rating_count' => 0, 'published_at' => $now,
                    'meta' => json_encode(['seed_source' => $data['version'], 'price_basis' => 'Provisional INR price for a young nursery plant; owner must verify size, cost and price before sale.', 'editorial_note' => $plant['care'], 'image_type' => 'illustration'], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($plant['categories'] as $slug) {
                    DB::table('product_categories')->insert([
                        'product_id' => $id, 'category_id' => $categories[$slug], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                DB::table('plant_profiles')->insert([
                    'product_id' => $id, 'common_name' => $plant['name'], 'scientific_name' => $plant['scientific_name'],
                    'indoor_outdoor' => $plant['indoor_outdoor'], 'sunlight' => $plant['sunlight'],
                    'water_requirement' => $plant['water_requirement'], 'difficulty_level' => $plant['difficulty_level'],
                    'soil_type' => in_array('succulents', $plant['categories'], true) ? 'Gritty, freely draining potting mix' : 'Well-drained container potting mix',
                    'growing_instructions' => $plant['care'].' Adjust watering to actual soil moisture, season and container size rather than a fixed schedule.',
                    'planting_instructions' => 'Use a container with drainage holes. Acclimatise gradually after delivery; avoid immediate heavy feeding.',
                    'pet_safety' => 'unknown',
                    'toxicity_info' => 'Not sold as food, medicine or pet feed. Keep away from children and pets; confirm species-specific safety before use.',
                    'meta' => json_encode(['seed_source' => $data['version'], 'care_note' => 'General starting guidance; adapt to local growing conditions.'], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $imagePath = '/catalog/starter/'.$plant['slug'].'.svg';
                $imageAlt = $plant['name'].' — illustrative artwork, not a product photograph';
                if (is_file(base_path('public/catalog/detail/'.$plant['slug'].'.webp'))) {
                    $imagePath = '/catalog/detail/'.$plant['slug'].'.webp';
                    $imageAlt = $plant['name'].' product photograph';
                }

                DB::table('product_images')->insert([
                    'product_id' => $id, 'url' => $baseUrl.$imagePath,
                    'alt' => $imageAlt,
                    'is_primary' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('inventory_items')->insert([
                    'product_id' => $id, 'warehouse_id' => $warehouseId,
                    'qty_on_hand' => 0, 'qty_reserved' => 0, 'qty_damaged' => 0, 'low_stock_threshold' => 5,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $result['created']++;
            }
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
            throw $e;
        }
        if (! $dryRun) {
            HomeService::forgetCache();
        }

        return $result;
    }
}
