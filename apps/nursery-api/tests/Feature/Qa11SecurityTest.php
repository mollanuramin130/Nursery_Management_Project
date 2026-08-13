<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\ReturnItem;
use App\Modules\Order\Models\ReturnRequest;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-11 — Security: RBAC, IDOR, privilege escalation, logout, sensitive fields.
 */
class Qa11SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function customer(string $email): User
    {
        $user = User::query()->create([
            'name' => 'QA11 Cust',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function staff(array $perms, string $email): User
    {
        $slug = 'qa11_'.substr(md5($email), 0, 12);
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => 'QA11 '.$slug],
        );
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );
        $user = User::query()->create([
            'name' => 'QA11 Staff',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function superAdmin(string $email = 'qa11-super@example.com'): User
    {
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user = User::query()->create([
            'name' => 'QA11 Super',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
    }

    private function asUser(User $user): self
    {
        JWTAuth::unsetToken();
        auth()->forgetGuards();
        $this->flushHeaders();
        $this->actingAs($user, 'api');

        return $this;
    }

    private function seedOrder(User $user, string $number): Order
    {
        $product = Product::query()->create([
            'name' => 'QA11 Plant',
            'slug' => 'qa11-plant-'.uniqid(),
            'sku' => 'QA11-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 100,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $order = Order::query()->create([
            'order_number' => $number,
            'user_id' => $user->id,
            'status' => 'CONFIRMED',
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
            'confirmed_at' => now(),
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

        return $order->fresh(['items']);
    }

    public function test_customer_cannot_access_admin_api(): void
    {
        $cust = $this->customer('qa11-cust-admin@example.com');
        $this->asUser($cust)->getJson('/api/v1/admin/orders')->assertStatus(403);
        $this->asUser($cust)->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->asUser($cust)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_permission_denials_and_allows(): void
    {
        $reader = $this->staff(['products.read'], 'qa11-prod-read@example.com');
        $this->asUser($reader)->getJson('/api/v1/admin/products?per_page=1')->assertOk();
        $this->asUser($reader)->postJson('/api/v1/admin/products', [
            'name' => 'X',
            'slug' => 'x-'.uniqid(),
            'sku' => 'X-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'draft',
            'price' => 10,
        ])->assertStatus(403);

        $inv = $this->staff(['inventory.view'], 'qa11-inv-view@example.com');
        $this->asUser($inv)->getJson('/api/v1/admin/inventory?per_page=1')->assertOk();
        $this->asUser($inv)->postJson('/api/v1/admin/inventory/adjust', [
            'warehouse_id' => 1,
            'product_id' => 1,
            'qty_delta' => 1,
            'reason' => 'test',
        ])->assertStatus(403);

        $ov = $this->staff(['orders.view'], 'qa11-ord-view@example.com');
        $this->asUser($ov)->getJson('/api/v1/admin/orders?per_page=1')->assertOk();
        $this->asUser($ov)->postJson('/api/v1/admin/orders/1/status', [
            'status' => 'PROCESSING',
        ])->assertStatus(403);

        $po = $this->staff(['purchase_orders.view'], 'qa11-po-view@example.com');
        $this->asUser($po)->getJson('/api/v1/admin/purchase-orders?per_page=1')->assertOk();
    }

    public function test_super_admin_bypasses_permission_middleware(): void
    {
        $super = $this->superAdmin();
        $this->asUser($super)->getJson('/api/v1/admin/users?per_page=5')->assertOk();
        $this->asUser($super)->getJson('/api/v1/admin/orders?per_page=5')->assertOk();
    }

    public function test_order_address_return_idor(): void
    {
        $a = $this->customer('qa11-a@example.com');
        $b = $this->customer('qa11-b@example.com');
        $orderA = $this->seedOrder($a, 'QA11-A-'.uniqid());
        $addrA = Address::query()->create([
            'user_id' => $a->id,
            'name' => 'A',
            'phone' => '9000000001',
            'line1' => 'L1',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $ret = ReturnRequest::query()->create([
            'order_id' => $orderA->id,
            'user_id' => $a->id,
            'status' => 'RETURN_REQUESTED',
            'notes' => 'damaged',
        ]);
        ReturnItem::query()->create([
            'return_request_id' => $ret->id,
            'order_item_id' => $orderA->items()->first()->id,
            'quantity' => 1,
            'reason' => 'damaged',
        ]);

        $this->asUser($b)->getJson('/api/v1/orders/'.$orderA->id)->assertNotFound();
        $this->asUser($b)->putJson('/api/v1/customer/addresses/'.$addrA->id, [
            'name' => 'Hijack',
            'phone' => '9000000002',
            'line1' => 'Hack',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
        ])->assertNotFound();
        $this->asUser($b)->getJson('/api/v1/returns/'.$ret->id)->assertNotFound();

        $this->asUser($a)->getJson('/api/v1/orders/'.$orderA->id)->assertOk();
        $this->asUser($a)->getJson('/api/v1/returns/'.$ret->id)->assertOk();
    }

    public function test_customer_cannot_mass_assign_admin_fields_on_profile(): void
    {
        $cust = $this->customer('qa11-mass@example.com');
        $res = $this->asUser($cust)->putJson('/api/v1/customer/profile', [
            'name' => 'Safe Name',
            'phone' => '9888888888',
            'status' => 'active',
            'role_id' => 1,
            'roles' => ['super_admin'],
            'password' => 'Hacked@123',
            'is_admin' => true,
        ]);
        $res->assertOk();
        $cust->refresh();
        $this->assertSame('Safe Name', $cust->name);
        $this->assertFalse(in_array('super_admin', $cust->roleSlugs(), true));
        $this->assertTrue(Hash::check('Secret@123', $cust->password));
        $body = $res->json('data');
        $this->assertArrayNotHasKey('password', $body ?? []);
    }

    public function test_users_manage_cannot_assign_super_admin(): void
    {
        $admin = $this->staff(['users.manage'], 'qa11-escalate@example.com');
        Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);

        $create = $this->asUser($admin)->postJson('/api/v1/admin/users', [
            'name' => 'Evil',
            'email' => 'evil.qa11@example.com',
            'password' => 'Secret@123',
            'status' => 'active',
            'role_slugs' => ['super_admin'],
        ]);
        $create->assertStatus(403);

        $ok = $this->asUser($admin)->postJson('/api/v1/admin/users', [
            'name' => 'Ops',
            'email' => 'ops.qa11@example.com',
            'password' => 'Secret@123',
            'status' => 'active',
            'role_slugs' => ['order_manager'],
        ]);
        $ok->assertCreated();
        $id = (int) $ok->json('data.id');

        $this->asUser($admin)->putJson('/api/v1/admin/users/'.$id, [
            'role_slugs' => ['super_admin'],
        ])->assertStatus(403);
    }

    public function test_super_admin_can_assign_super_admin(): void
    {
        $super = $this->superAdmin();
        $create = $this->asUser($super)->postJson('/api/v1/admin/users', [
            'name' => 'Peer Super',
            'email' => 'peer.super.qa11@example.com',
            'password' => 'Secret@123',
            'status' => 'active',
            'role_slugs' => ['super_admin'],
        ]);
        $create->assertCreated();
        $this->assertContains('super_admin', $create->json('data.roles'));
    }

    public function test_login_failure_and_me_has_no_password(): void
    {
        $this->customer('qa11-login@example.com');
        $this->postJson('/api/v1/auth/login', [
            'email' => 'qa11-login@example.com',
            'password' => 'WrongPassword1',
            'device' => ['platform' => 'web'],
        ])->assertStatus(401);

        $ok = $this->postJson('/api/v1/auth/login', [
            'email' => 'qa11-login@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ])->assertOk();
        $this->assertArrayNotHasKey('password', $ok->json('data.user') ?? []);
        $token = $ok->json('data.access_token');
        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
        $this->assertArrayNotHasKey('password', $me->json('data') ?? []);
    }

    public function test_logout_invalidates_refresh_token(): void
    {
        $this->customer('qa11-logout@example.com');
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'qa11-logout@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ])->assertOk()->json('data');

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $login['refresh_token'],
            ])
            ->assertOk();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ])->assertStatus(401);
    }

    public function test_forgot_password_does_not_enumerate_emails(): void
    {
        $known = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody-qa11@example.com',
        ]);
        $known->assertSuccessful();
        $this->assertTrue((bool) $known->json('success'));
    }

    public function test_security_headers_present_on_api(): void
    {
        $res = $this->getJson('/api/v1/health/ready');
        $res->assertOk();
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $res->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($res->headers->get('Referrer-Policy'));
    }
}
