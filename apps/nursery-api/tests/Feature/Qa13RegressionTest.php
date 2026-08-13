<?php

namespace Tests\Feature;

use App\Modules\Admin\Services\AnalyticsService;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderStateMachine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-13 — Automated regression gaps: negatives, boundaries, analytics assessment, idempotency.
 */
class Qa13RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(string $email = 'qa13@example.com'): User
    {
        $user = User::query()->create([
            'name' => 'QA13',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function analyst(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['reports.view', 'reports.export'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['reports.view', 'reports.export'])->pluck('id')
        );
        $user = User::query()->create([
            'name' => 'QA13 Analyst',
            'email' => 'qa13-analyst@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function productWithStock(int $qty = 5, float $price = 199, string $status = 'active'): Product
    {
        $product = Product::query()->create([
            'name' => 'QA13 Plant',
            'slug' => 'qa13-'.uniqid(),
            'sku' => 'QA13-'.uniqid(),
            'product_type' => 'plant',
            'status' => $status,
            'price' => $price,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA13'],
            ['name' => 'QA13 WH', 'status' => 'active'],
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

    private function asUser(User $user): self
    {
        JWTAuth::unsetToken();
        auth()->forgetGuards();

        return $this->actingAs($user, 'api');
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $user = $this->customer();
        $this->asUser($user)->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->asUser($user)->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->asUser($user)->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_product_show_404_for_missing_and_inactive_hidden(): void
    {
        $this->getJson('/api/v1/products/999999')->assertNotFound();

        $inactive = $this->productWithStock(status: 'inactive');
        // Public catalog show should not expose inactive as success listing path.
        $list = $this->getJson('/api/v1/products?per_page=50')->assertOk()->json('data');
        $ids = collect($list)->pluck('id')->all();
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_products_per_page_is_capped(): void
    {
        $response = $this->getJson('/api/v1/products?per_page=999999')->assertOk();
        $perPage = (int) data_get($response->json(), 'meta.pagination.per_page');
        $this->assertGreaterThan(0, $perPage);
        $this->assertLessThanOrEqual(100, $perPage);
    }

    public function test_cart_rejects_zero_quantity_and_oversell(): void
    {
        $user = $this->customer('qa13-cart@example.com');
        $product = $this->productWithStock(2);

        $this->asUser($user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 0,
            ])
            ->assertStatus(422);

        $this->asUser($user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 99,
            ])
            ->assertStatus(409);
    }

    public function test_invalid_coupon_rejected(): void
    {
        $user = $this->customer('qa13-coupon@example.com');
        $product = $this->productWithStock(5);
        $this->asUser($user)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->asUser($user)
            ->postJson('/api/v1/cart/apply-coupon', ['code' => 'NO_SUCH_COUPON_XYZ'])
            ->assertStatus(400);
    }

    public function test_invalid_order_status_transition_rejected(): void
    {
        $user = $this->customer('qa13-order@example.com');
        $order = Order::query()->create([
            'order_number' => 'QA13-ORD-'.uniqid(),
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

        $this->expectException(\App\Shared\Exceptions\ApiException::class);
        app(OrderStateMachine::class)->transition($order, 'CONFIRMED');
    }

    public function test_cod_idempotent_same_request_id(): void
    {
        $user = $this->customer('qa13-cod@example.com');
        $product = $this->productWithStock(10, 149);
        $headers = [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
            'X-Request-Id' => 'qa13_cod_idem_1',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA13',
            'phone' => '9999999999',
            'line1' => '1 Test',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD-QA13',
            'name' => 'Standard',
            'price' => 49,
            'status' => 'active',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
        ]);

        $payload = [
            'address_id' => $address->id,
            'shipping_method_id' => $shipping->id,
            'payment_method' => 'cod',
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/orders', $payload)
            ->assertSuccessful();
        $orderId = data_get($first->json(), 'data.id') ?? data_get($first->json(), 'data.order.id');
        $this->assertNotNull($orderId);

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/orders', $payload)
            ->assertSuccessful();
        $orderId2 = data_get($second->json(), 'data.id') ?? data_get($second->json(), 'data.order.id');
        $this->assertSame((int) $orderId, (int) $orderId2);
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    /**
     * QA-PERF-010 assessment: inventory analytics hydrates all locations then returns ≤100 rows.
     * Correctness OK; growth risk remains OPEN (documented, not auto-optimized in QA-13).
     */
    public function test_analytics_inventory_rows_are_capped_at_100(): void
    {
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA13-AN'],
            ['name' => 'QA13 AN', 'status' => 'active'],
        );
        for ($i = 0; $i < 12; $i++) {
            $p = Product::query()->create([
                'name' => "QA13 AN $i",
                'slug' => 'qa13-an-'.uniqid(),
                'sku' => 'QA13AN-'.uniqid(),
                'product_type' => 'plant',
                'status' => 'active',
                'price' => 10 + $i,
                'currency' => 'INR',
                'stock_status' => 'in_stock',
            ]);
            InventoryItem::query()->create([
                'warehouse_id' => $wh->id,
                'product_id' => $p->id,
                'qty_on_hand' => $i,
                'qty_reserved' => 0,
                'qty_damaged' => 0,
                'low_stock_threshold' => 5,
            ]);
        }

        $result = app(AnalyticsService::class)->inventory();
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertLessThanOrEqual(100, count($result['rows']));
        $this->assertGreaterThanOrEqual(12, (int) $result['summary']['sku_locations']);

        $staff = $this->analyst();
        $this->asUser($staff)
            ->getJson('/api/v1/admin/analytics/inventory')
            ->assertOk()
            ->assertJsonPath('data.summary.sku_locations', $result['summary']['sku_locations']);
    }

    public function test_analytics_campaigns_endpoint_returns_support_flag(): void
    {
        $staff = $this->analyst();
        $this->asUser($staff)
            ->getJson('/api/v1/admin/analytics/campaigns')
            ->assertOk()
            ->assertJsonStructure(['data' => ['supported', 'coupon_performance_available']]);
    }

    public function test_free_delivery_threshold_boundaries(): void
    {
        putenv('FREE_DELIVERY_THRESHOLD=500');
        $_ENV['FREE_DELIVERY_THRESHOLD'] = '500';

        $svc = app(\App\Modules\Cart\Services\CartService::class);
        $below = $svc->freeDeliveryMeta(499.0);
        $exact = $svc->freeDeliveryMeta(500.0);
        $above = $svc->freeDeliveryMeta(501.0);

        $this->assertFalse($below['qualifies']);
        $this->assertEquals(1.0, $below['remaining']);
        $this->assertTrue($exact['qualifies']);
        $this->assertTrue($above['qualifies']);
    }
}
