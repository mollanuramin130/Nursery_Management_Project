<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Services\HomeService;
use App\Shared\Rules\SecureImageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class Phase12HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomerRole(): void
    {
        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer'],
        );
    }

    public function test_security_headers_present_on_api(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_health_live_and_ready(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'healthy');
    }

    public function test_request_id_sanitized_when_spoofed(): void
    {
        $response = $this->withHeader('X-Request-Id', "bad\nid<script>")
            ->getJson('/api/v1/health');

        $response->assertOk();
        $id = $response->headers->get('X-Request-Id');
        $this->assertDoesNotMatchRegularExpression('/[\r\n<>]/', (string) $id);
        $this->assertStringStartsWith('req_', (string) $id);
    }

    public function test_weak_password_rejected_on_register(): void
    {
        $this->seedCustomerRole();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak User',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422);
    }

    public function test_strong_password_register_succeeds(): void
    {
        $this->seedCustomerRole();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Strong User',
            'email' => 'strong@example.com',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertJsonPath('success', true);
    }

    public function test_user_status_not_mass_assignable(): void
    {
        $user = User::query()->create([
            'name' => 'Assign Test',
            'email' => 'assign@example.com',
            'password' => Hash::make('Secret@123'),
            'status' => 'blocked',
        ]);

        $this->assertNotSame('blocked', $user->fresh()->status);
        $user->forceFill(['status' => 'blocked'])->save();
        $this->assertSame('blocked', $user->fresh()->status);
    }

    public function test_home_feed_is_cached(): void
    {
        Cache::flush();
        $home = app(HomeService::class);
        $first = $home->feed();
        $this->assertTrue(Cache::has(HomeService::CACHE_KEY));
        $second = $home->feed();
        $this->assertSame($first, $second);
        HomeService::forgetCache();
        $this->assertFalse(Cache::has(HomeService::CACHE_KEY));
    }

    public function test_secure_image_url_rule(): void
    {
        $bad = Validator::make(
            ['url' => 'javascript:alert(1)'],
            ['url' => [new SecureImageUrl]],
        );
        $this->assertTrue($bad->fails());

        $good = Validator::make(
            ['url' => 'https://cdn.example.com/plant.jpg'],
            ['url' => [new SecureImageUrl]],
        );
        $this->assertFalse($good->fails());
    }
}
