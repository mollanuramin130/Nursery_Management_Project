<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-09 — Admin ops API contracts used by Admin Mobile.
 */
class Qa09AdminOpsMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staff(array $perms, string $email = 'qa09-ops@example.com'): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'qa09_ops'],
            ['name' => 'QA09 Ops'],
        );
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );
        $user = User::query()->create([
            'name' => 'QA09 Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    public function test_ops_dashboard_orders_inventory_fulfillment_require_auth(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/admin/orders')->assertStatus(401);
        $this->getJson('/api/v1/admin/inventory')->assertStatus(401);
        $this->getJson('/api/v1/admin/fulfillment')->assertStatus(401);
    }

    public function test_ops_staff_can_reach_core_endpoints(): void
    {
        $user = $this->staff([
            'reports.view',
            'orders.view',
            'inventory.view',
            'fulfillment.view',
            'purchase_orders.view',
            'suppliers.view',
            'warehouses.view',
            'notifications.view',
        ]);
        $token = JWTAuth::fromUser($user);
        $h = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($h)->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/admin/orders?per_page=5')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/admin/inventory?per_page=5')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/admin/fulfillment')->assertOk();
        // purchase_orders.view alone must pass (middleware OR across comma args — QA-09)
        $this->withHeaders($h)->getJson('/api/v1/admin/purchase-orders?per_page=5')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/admin/suppliers?per_page=5')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/admin/warehouses')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/notifications')->assertOk();
    }

    public function test_purchase_orders_view_alone_is_enough_without_inventory_adjust(): void
    {
        $user = $this->staff(['purchase_orders.view'], 'qa09-po-only@example.com');
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/purchase-orders?per_page=5')
            ->assertOk();
    }

    public function test_missing_permission_returns_403_not_401(): void
    {
        $user = $this->staff(['orders.view'], 'qa09-limited@example.com');
        $token = JWTAuth::fromUser($user);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users');
        $res->assertStatus(403);
    }

    public function test_refresh_issues_new_tokens(): void
    {
        $user = $this->staff(['orders.view'], 'qa09-refresh@example.com');
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret@123',
            'device' => ['platform' => 'android', 'app' => 'admin_mobile'],
        ])->assertOk();

        $refresh = $login->json('data.refresh_token');
        $this->assertNotEmpty($refresh);

        $ref = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh,
        ])->assertOk();

        $this->assertNotEmpty($ref->json('data.access_token'));
        $this->assertNotEmpty($ref->json('data.refresh_token'));
    }
}
