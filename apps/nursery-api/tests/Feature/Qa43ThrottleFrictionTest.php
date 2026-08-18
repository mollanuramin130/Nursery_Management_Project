<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Shared\Support\RateLimiterConfigurator;
use App\Shared\Support\ThrottleLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Qa43ThrottleFrictionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'qa43-auth@example.com'): User
    {
        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer'],
        );

        $user = User::query()->create([
            'name' => 'QA43 Auth',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();

        return $user;
    }

    public function test_testing_environment_uses_relaxed_limits(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertGreaterThanOrEqual(1000, ThrottleLimits::apiPerMinute());
        $this->assertGreaterThanOrEqual(60, ThrottleLimits::loginPerMinute());
    }

    public function test_health_and_webhooks_skip_global_api_limiter(): void
    {
        $this->assertTrue(RateLimiterConfigurator::skipsGlobalApiLimit(
            Request::create('/api/v1/health/ready', 'GET'),
        ));
        $this->assertTrue(RateLimiterConfigurator::skipsGlobalApiLimit(
            Request::create('/api/v1/payments/webhooks/razorpay', 'POST'),
        ));
        $this->assertFalse(RateLimiterConfigurator::skipsGlobalApiLimit(
            Request::create('/api/v1/auth/login', 'POST'),
        ));
        $this->assertFalse(RateLimiterConfigurator::skipsGlobalApiLimit(
            Request::create('/api/v1/products', 'GET'),
        ));
    }

    public function test_rapid_catalog_reads_do_not_block_valid_login(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 80; $i++) {
            $this->getJson('/api/v1/products?per_page=1')->assertOk();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'qa43-auth@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_login_limiter_still_blocks_burst_when_configured_low(): void
    {
        config(['throttling.login_per_minute' => 2]);
        $this->makeUser('qa43-burst@example.com');

        $payload = [
            'email' => 'qa43-burst@example.com',
            'password' => 'WrongPass1',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('meta.error_code', 'RATE_LIMITED');
    }

    public function test_valid_login_twice_in_a_row_succeeds_under_testing_limits(): void
    {
        $this->makeUser('qa43-repeat@example.com');

        $payload = [
            'email' => 'qa43-repeat@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertOk();
        $this->postJson('/api/v1/auth/login', $payload)->assertOk();
    }
}
