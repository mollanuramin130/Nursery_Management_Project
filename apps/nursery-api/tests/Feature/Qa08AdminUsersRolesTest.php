<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Qa08AdminUsersRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staffWith(array $permissionSlugs, string $email = 'qa08-staff@example.com'): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'qa08_staff'],
            ['name' => 'QA08 Staff'],
        );
        $role->permissions()->sync(
            \App\Modules\Auth\Models\Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id')
        );

        $user = User::query()->create([
            'name' => 'QA08 Staff',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    public function test_users_manage_can_list_and_show_with_permissions(): void
    {
        $admin = $this->staffWith(['users.manage']);
        $token = JWTAuth::fromUser($admin);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users?per_page=10');
        $list->assertOk()->assertJsonPath('success', true);

        $show = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users/'.$admin->id);
        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'roles',
                    'permissions',
                    'role_permissions',
                ],
            ]);
        $this->assertContains('users.manage', $show->json('data.permissions'));
    }

    public function test_roles_catalog_requires_users_manage(): void
    {
        $denied = $this->staffWith(['orders.view'], 'qa08-no-users@example.com');
        $token = JWTAuth::fromUser($denied);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/roles')
            ->assertStatus(403);

        $admin = $this->staffWith(['users.manage'], 'qa08-roles@example.com');
        $ok = $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($admin))
            ->getJson('/api/v1/admin/roles');
        $ok->assertOk()->assertJsonPath('success', true);
        $slugs = collect($ok->json('data'))->pluck('slug')->all();
        $this->assertContains('admin', $slugs);
        $this->assertContains('customer', $slugs);
    }

    public function test_users_without_permission_get_403_not_401(): void
    {
        $user = $this->staffWith(['products.read'], 'qa08-deny@example.com');
        $token = JWTAuth::fromUser($user);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users');
        $res->assertStatus(403);
        $this->assertNotSame(401, $res->status());
    }

    public function test_create_update_and_status_change(): void
    {
        $admin = $this->staffWith(['users.manage'], 'qa08-crud@example.com');
        $token = JWTAuth::fromUser($admin);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Ops User',
                'email' => 'ops.qa08@example.com',
                'password' => 'Secret@123',
                'status' => 'active',
                'role_slugs' => ['order_manager'],
            ]);
        $create->assertCreated()->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$id, [
                'status' => 'inactive',
                'role_slugs' => ['customer_support'],
            ]);
        $update->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.roles.0', 'customer_support');
    }

    public function test_production_refund_stub_rejected(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $admin = $this->staffWith(['payments.refund'], 'qa08-refund@example.com');
        $customer = User::query()->create([
            'name' => 'Cust',
            'email' => 'cust.qa08@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $customer->forceFill(['status' => 'active'])->save();
        $customer->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $order = Order::query()->create([
            'order_number' => 'QA08-R-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'razorpay',
            'shipping_address_json' => ['name' => 'A'],
            'billing_address_json' => ['name' => 'A'],
            'placed_at' => now()->subDays(2),
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'razorpay',
            'method' => 'card',
            'amount' => 100,
            'currency' => 'INR',
            'status' => 'paid',
            'idempotency_key' => 'qa08-pay-'.uniqid(),
            'provider_order_id' => 'order_qa08',
            'provider_payment_id' => 'pay_qa08',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($admin))
            ->postJson('/api/v1/admin/refunds', [
                'order_id' => $order->id,
                'amount' => 10,
                'reason' => 'QA-08 production guard',
                'idempotency_key' => 'qa08-refund-1',
            ]);

        $res->assertStatus(503);
        $this->assertStringContainsString('not enabled', (string) $res->json('message'));
    }
}
