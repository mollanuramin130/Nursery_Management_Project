<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
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
}
