<?php

namespace Tests\Feature;

use App\Shared\Support\ProductionReadinessChecker;
use Tests\TestCase;

/**
 * QA-16 — Production readiness checker (config gates, not live Razorpay).
 */
class Qa16ProductionHardeningTest extends TestCase
{
    public function test_production_profile_fails_when_debug_true(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('production', true, [
            'debug' => true,
            'razorpay_key' => 'rzp_live_x',
            'razorpay_secret' => 'secret',
            'razorpay_webhook' => 'whsec',
            'cors' => 'https://shop.example.com,https://admin.example.com',
            'app_url' => 'https://api.example.com',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt-secret-value',
        ]);

        $codes = array_column($findings, 'code');
        $this->assertContains('APP_DEBUG_ON', $codes);
        $this->assertFalse($checker->isReady('production', true, [
            'debug' => true,
            'razorpay_key' => 'rzp_live_x',
            'razorpay_secret' => 'secret',
            'razorpay_webhook' => 'whsec',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
            'jwt_secret' => 'jwt',
        ]));
    }

    public function test_production_requires_razorpay_and_https_cors(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => '',
            'razorpay_secret' => '',
            'razorpay_webhook' => '',
            'cors' => 'http://localhost:3000',
            'app_url' => 'http://api.example.com',
            'allow_unsigned_webhooks' => true,
            'jwt_secret' => '',
        ]);
        $codes = array_column($findings, 'code');

        $this->assertContains('RAZORPAY_KEY_MISSING', $codes);
        $this->assertContains('RAZORPAY_SECRET_MISSING', $codes);
        $this->assertContains('RAZORPAY_WEBHOOK_MISSING', $codes);
        $this->assertContains('CORS_LOCALHOST', $codes);
        $this->assertContains('APP_URL_NOT_HTTPS', $codes);
        $this->assertContains('UNSIGNED_WEBHOOKS_ENABLED', $codes);
        $this->assertContains('JWT_SECRET_MISSING', $codes);
    }

    public function test_healthy_production_profile_is_ready(): void
    {
        $checker = new ProductionReadinessChecker;
        $config = [
            'debug' => false,
            'razorpay_key' => 'rzp_live_ok',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://shop.example.com,https://admin.example.com',
            'app_url' => 'https://api.example.com',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'strong-jwt-secret',
        ];
        $this->assertSame([], $checker->evaluate('production', false, $config));
        $this->assertTrue($checker->isReady('production', false, $config));
    }

    public function test_local_env_does_not_require_razorpay_keys(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('local', true, [
            'debug' => true,
            'razorpay_key' => '',
            'razorpay_secret' => '',
            'razorpay_webhook' => '',
            'cors' => 'http://localhost:3000',
            'app_url' => 'http://localhost:8000',
            'jwt_secret' => 'local-jwt',
        ]);
        $this->assertSame([], $findings);
    }

    public function test_artisan_production_readiness_command_runs(): void
    {
        $this->artisan('nursery:production-readiness')
            ->assertSuccessful();
    }

    public function test_strict_gate_rejects_non_production_env(): void
    {
        // Local/testing PHPUnit env is never "production".
        $this->artisan('nursery:production-readiness', ['--strict' => true])
            ->assertFailed();
    }

    public function test_production_rejects_razorpay_test_key_prefix(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_should_not_ship',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt',
        ]);
        $codes = array_column($findings, 'code');
        $this->assertContains('RAZORPAY_TEST_KEY_IN_PRODUCTION', $codes);
        $this->assertFalse($checker->isReady('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_should_not_ship',
            'razorpay_secret' => 'sec',
            'razorpay_webhook' => 'wh',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
            'jwt_secret' => 'jwt',
        ]));
    }
}
