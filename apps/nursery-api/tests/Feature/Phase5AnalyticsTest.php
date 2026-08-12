<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase5AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithReports(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['reports.view', 'reports.export'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Analyst',
            'email' => 'analyst@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['reports.view', 'reports.export'])->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    private function seedOrders(User $customer): void
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer->roles()->syncWithoutDetaching(
            Role::query()->where('slug', 'customer')->pluck('id')
        );

        $p1 = Product::query()->create([
            'name' => 'Plant A', 'slug' => 'plant-a-p5', 'sku' => 'A',
            'product_type' => 'plant', 'status' => 'active', 'price' => 1000, 'currency' => 'INR',
            'rating_avg' => 0, 'rating_count' => 0,
        ]);
        $p2 = Product::query()->create([
            'name' => 'Plant B', 'slug' => 'plant-b-p5', 'sku' => 'B',
            'product_type' => 'plant', 'status' => 'active', 'price' => 2000, 'currency' => 'INR',
            'rating_avg' => 0, 'rating_count' => 0,
        ]);

        $addr = [
            'name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Pune',
            'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
        ];

        $a = Order::query()->create([
            'order_number' => 'GL-A5-1',
            'user_id' => $customer->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 1000,
            'discount_total' => 100,
            'tax_total' => 95,
            'shipping_total' => 50,
            'grand_total' => 1045,
            'payment_method' => 'razorpay',
            'shipping_address_json' => $addr,
            'placed_at' => now()->subDays(2),
        ]);
        $a->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        OrderItem::query()->create([
            'order_id' => $a->id,
            'product_id' => $p1->id,
            'name' => 'Plant A',
            'sku' => 'A',
            'unit_price' => 1000,
            'quantity' => 1,
            'line_total' => 1000,
        ]);

        $b = Order::query()->create([
            'order_number' => 'GL-A5-2',
            'user_id' => $customer->id,
            'status' => 'CONFIRMED',
            'currency' => 'INR',
            'subtotal' => 2000,
            'discount_total' => 200,
            'tax_total' => 180,
            'shipping_total' => 0,
            'grand_total' => 1980,
            'payment_method' => 'cod',
            'shipping_address_json' => $addr,
            'placed_at' => now()->subDay(),
        ]);
        $b->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        OrderItem::query()->create([
            'order_id' => $b->id,
            'product_id' => $p2->id,
            'name' => 'Plant B',
            'sku' => 'B',
            'unit_price' => 2000,
            'quantity' => 1,
            'line_total' => 2000,
        ]);

        $x = Order::query()->create([
            'order_number' => 'GL-A5-X',
            'user_id' => $customer->id,
            'status' => 'CANCELLED',
            'currency' => 'INR',
            'subtotal' => 500,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 500,
            'payment_method' => 'cod',
            'shipping_address_json' => $addr,
            'placed_at' => now()->subDays(3),
        ]);
        $x->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();
    }

    public function test_sales_analytics_excludes_cancelled_and_matches_formula(): void
    {
        $staff = $this->staffWithReports();
        $customer = User::query()->create([
            'name' => 'Buyer',
            'email' => 'buyer5@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $customer->forceFill(['status' => 'active'])->save();
        $this->seedOrders($customer);
        $token = JWTAuth::fromUser($staff);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/sales?preset=last_30_days')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Revenue = 1045 + 1980 = 3025 (cancelled excluded)
        $res->assertJsonPath('data.summary.revenue', 3025);
        $res->assertJsonPath('data.summary.orders', 2);
        $res->assertJsonPath('data.summary.discount_total', 300);
        $res->assertJsonPath('data.summary.shipping_total', 50);
        $res->assertJsonPath('data.summary.tax_total', 275);
        // AOV = 3025 / 2 = 1512.5
        $res->assertJsonPath('data.summary.average_order_value', 1512.5);
    }

    public function test_customer_cannot_access_analytics(): void
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer = User::query()->create([
            'name' => 'Cust',
            'email' => 'cust5@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $customer->forceFill(['status' => 'active'])->save();
        $customer->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));
        $token = JWTAuth::fromUser($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertStatus(403);
    }

    public function test_campaigns_endpoint_is_explicitly_unsupported(): void
    {
        $staff = $this->staffWithReports();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/campaigns')
            ->assertOk()
            ->assertJsonPath('data.supported', false);
    }

    public function test_export_requires_reports_export_permission(): void
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Permission::query()->firstOrCreate(['slug' => 'reports.view'], ['name' => 'reports.view']);
        $user = User::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer5@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->sync(
            Permission::query()->where('slug', 'reports.view')->pluck('id')
        );
        $token = JWTAuth::fromUser($user->fresh(['roles.permissions']));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/export?type=sales&preset=today')
            ->assertStatus(403);
    }
}
