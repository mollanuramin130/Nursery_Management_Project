<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\PlantProfile;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StockAlertSubscription;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase16CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'P16 Customer',
            'email' => 'p16-customer@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'customer')->first();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
    }

    public function test_product_view_and_recently_viewed(): void
    {
        $product = Product::query()->create([
            'name' => 'Viewed Plant',
            'slug' => 'viewed-plant-p16',
            'sku' => 'P16-VIEW',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 299,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);

        $this->withHeader('X-Guest-Token', 'guest-token-abcdef')
            ->postJson('/api/v1/product-views', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('product_views', [
            'product_id' => $product->id,
            'guest_token' => 'guest-token-abcdef',
        ]);

        $this->withHeader('X-Guest-Token', 'guest-token-abcdef')
            ->getJson('/api/v1/recently-viewed')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_search_assist_and_home_definitions(): void
    {
        Product::query()->create([
            'name' => 'Popular Plant',
            'slug' => 'popular-p16',
            'sku' => 'P16-POP',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
            'rating_count' => 10,
            'rating_avg' => 4.5,
        ]);

        $this->getJson('/api/v1/search/assist?q=low%20light%20indoor')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['filters', 'popular_products', 'plant_finder']]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['definitions', 'recommended_for_you']]);
    }

    public function test_stock_alert_subscribe_and_notify_on_restock(): void
    {
        $customer = $this->customer();
        $token = JWTAuth::fromUser($customer);

        $product = Product::query()->create([
            'name' => 'OOS Plant',
            'slug' => 'oos-p16',
            'sku' => 'P16-OOS',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 150,
            'currency' => 'INR',
            'stock_status' => 'out_of_stock',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-P16',
            'name' => 'Phase16 WH',
            'status' => 'active',
        ]);

        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/products/'.$product->id.'/stock-alert')
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('stock_alert_subscriptions', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        app(InventoryService::class)->adjust([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'adjustment' => 5,
            'reason' => 'purchase_in',
            'note' => 'p16 restock',
        ]);

        $this->assertDatabaseHas('stock_alert_subscriptions', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'notified',
        ]);
    }

    public function test_cart_warnings_envelope_present(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => ['warnings', 'checkout_blocked', 'free_delivery']]);
    }

    public function test_placement_both_returns_indoor_and_outdoor_plants(): void
    {
        $indoor = Product::query()->create([
            'name' => 'Indoor P16',
            'slug' => 'indoor-p16',
            'sku' => 'P16-IN',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $outdoor = Product::query()->create([
            'name' => 'Outdoor P16',
            'slug' => 'outdoor-p16',
            'sku' => 'P16-OUT',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 249,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        PlantProfile::query()->create([
            'product_id' => $indoor->id,
            'common_name' => 'Indoor P16',
            'indoor_outdoor' => 'indoor',
            'sunlight' => 'low',
            'water_requirement' => 'low',
            'difficulty_level' => 'easy',
        ]);
        PlantProfile::query()->create([
            'product_id' => $outdoor->id,
            'common_name' => 'Outdoor P16',
            'indoor_outdoor' => 'outdoor',
            'sunlight' => 'full_sun',
            'water_requirement' => 'medium',
            'difficulty_level' => 'easy',
        ]);

        $both = $this->getJson('/api/v1/products?indoor_outdoor=both&product_type=plant&per_page=50')
            ->assertOk()
            ->json('data');
        $ids = collect($both)->pluck('id')->all();
        $this->assertContains($indoor->id, $ids);
        $this->assertContains($outdoor->id, $ids);

        $indoorOnly = $this->getJson('/api/v1/products?indoor_outdoor=indoor&product_type=plant&per_page=50')
            ->assertOk()
            ->json('data');
        $indoorIds = collect($indoorOnly)->pluck('id')->all();
        $this->assertContains($indoor->id, $indoorIds);
        $this->assertNotContains($outdoor->id, $indoorIds);
    }

    public function test_catalog_filter_aliases_match_stored_attributes(): void
    {
        $plant = Product::query()->create([
            'name' => 'Alias Plant P16',
            'slug' => 'alias-p16',
            'sku' => 'P16-ALIAS',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $kit = Product::query()->create([
            'name' => 'Starter Bundle P16',
            'slug' => 'bundle-p16',
            'sku' => 'P16-BUN',
            'product_type' => 'bundle',
            'status' => 'active',
            'price' => 499,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $moderate = Product::query()->create([
            'name' => 'Moderate Plant P16',
            'slug' => 'moderate-p16',
            'sku' => 'P16-MOD',
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 299,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        PlantProfile::query()->create([
            'product_id' => $plant->id,
            'common_name' => 'Alias Plant P16',
            'indoor_outdoor' => 'indoor',
            'sunlight' => 'bright_indirect',
            'water_requirement' => 'medium',
            'difficulty_level' => 'easy',
            'pet_safety' => 'safe',
        ]);
        PlantProfile::query()->create([
            'product_id' => $moderate->id,
            'common_name' => 'Moderate Plant P16',
            'indoor_outdoor' => 'outdoor',
            'sunlight' => 'partial',
            'water_requirement' => 'medium',
            'difficulty_level' => 'moderate',
            'pet_safety' => 'toxic',
        ]);

        $brightIds = collect(
            $this->getJson('/api/v1/products?sunlight=bright&product_type=plant&per_page=50')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();
        $this->assertContains($plant->id, $brightIds);
        $this->assertContains($moderate->id, $brightIds);

        $petIds = collect(
            $this->getJson('/api/v1/products?pet_safety=pet_safe&per_page=50')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();
        $this->assertContains($plant->id, $petIds);
        $this->assertNotContains($moderate->id, $petIds);

        $beginnerIds = collect(
            $this->getJson('/api/v1/products?q=beginner&per_page=50')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();
        $this->assertContains($plant->id, $beginnerIds);

        $kitIds = collect(
            $this->getJson('/api/v1/products?product_type=kit&per_page=50')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();
        $this->assertContains($kit->id, $kitIds);

        $expertIds = collect(
            $this->getJson('/api/v1/products?difficulty_level=advanced&product_type=plant&per_page=50')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();
        $this->assertContains($moderate->id, $expertIds);
        $this->assertNotContains($plant->id, $expertIds);
    }
}
