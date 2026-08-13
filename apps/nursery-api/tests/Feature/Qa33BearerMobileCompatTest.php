<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * QA-33 — Mobile Bearer contract must remain intact while Web uses BFF cookies.
 */
class Qa33BearerMobileCompatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_still_returns_bearer_tokens_for_mobile_clients(): void
    {
        $user = User::query()->create([
            'name' => 'QA33 Mobile',
            'email' => 'qa33-mobile@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'qa33-mobile@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'android'],
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'user' => ['id', 'email']],
            ]);

        $access = $res->json('data.access_token');
        $this->assertIsString($access);
        $this->assertNotSame('', $access);

        $me = $this->withHeader('Authorization', 'Bearer '.$access)
            ->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('data.email', 'qa33-mobile@example.com');
    }

    public function test_customer_cannot_call_admin_orders(): void
    {
        $user = User::query()->create([
            'name' => 'QA33 Cust',
            'email' => 'qa33-cust@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'qa33-cust@example.com',
            'password' => 'Secret@123',
        ])->assertOk();

        $token = $login->json('data.access_token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(403);
    }
}
