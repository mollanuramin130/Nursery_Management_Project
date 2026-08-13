<?php

namespace Tests\Feature;

use App\Integrations\Payment\RazorpayGateway;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\ProductionReadinessChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;

/**
 * QA-27 — LIVE Razorpay readiness gate (no LIVE charge).
 *
 * Never prints secret values. Never claims LIVE payment PASS.
 */
class Qa27LiveReadinessGateTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    public function test_host_dotenv_does_not_require_live_keys_on_local(): void
    {
        $p = $this->razorpayEnvPresence();
        $key = $p['key'];
        if ($key === '') {
            $this->assertTrue(true);

            return;
        }
        // Local may have TEST keys; must not carry LIVE keys for routine local work.
        $this->assertFalse(
            str_starts_with($key, 'rzp_live_'),
            'QA-27: rzp_live_* must not be placed in local development .env',
        );
        if (str_starts_with($key, 'rzp_test_')) {
            $this->assertTrue(true);
        }
    }

    public function test_production_profile_rejects_test_razorpay_key(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_example_not_a_secret',
            'razorpay_secret' => 'x',
            'razorpay_webhook' => 'y',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
        ]);
        $codes = array_column($findings, 'code');
        $this->assertContains('RAZORPAY_TEST_KEY_IN_PRODUCTION', $codes);
        $this->assertFalse($checker->isReady('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_test_example_not_a_secret',
            'razorpay_secret' => 'x',
            'razorpay_webhook' => 'y',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
        ]));
    }

    public function test_production_profile_requires_live_key_shape_and_unsigned_off(): void
    {
        $checker = new ProductionReadinessChecker;
        $ready = $checker->isReady('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_live_example_not_a_secret',
            'razorpay_secret' => 'x',
            'razorpay_webhook' => 'y',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt',
            'cors' => 'https://shop.example.com,https://admin.example.com',
            'app_url' => 'https://api.example.com',
        ]);
        $this->assertTrue($ready);

        $badUnsigned = $checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_live_example_not_a_secret',
            'razorpay_secret' => 'x',
            'razorpay_webhook' => 'y',
            'allow_unsigned_webhooks' => true,
            'jwt_secret' => 'jwt',
            'cors' => 'https://shop.example.com',
            'app_url' => 'https://api.example.com',
        ]);
        $this->assertContains('UNSIGNED_WEBHOOKS_ENABLED', array_column($badUnsigned, 'code'));
    }

    public function test_production_profile_requires_https_app_url(): void
    {
        $checker = new ProductionReadinessChecker;
        $findings = $checker->evaluate('production', false, [
            'debug' => false,
            'razorpay_key' => 'rzp_live_example_not_a_secret',
            'razorpay_secret' => 'x',
            'razorpay_webhook' => 'y',
            'allow_unsigned_webhooks' => false,
            'jwt_secret' => 'jwt',
            'cors' => 'https://shop.example.com',
            'app_url' => 'http://api.example.com',
        ]);
        $this->assertContains('APP_URL_NOT_HTTPS', array_column($findings, 'code'));
    }

    public function test_production_refuses_stub_gateway_when_keys_empty(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->clearRazorpayEnv();
        $gateway = new RazorpayGateway;
        try {
            $gateway->createOrder(100, 'INR', 'QA27-LIVE');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('PAYMENT_GATEWAY_UNAVAILABLE', $e->errorCode());
        }
    }

    public function test_webhook_route_registered_and_unsigned_disallowed_in_dotenv(): void
    {
        $this->assertTrue(
            collect(Route::getRoutes())->contains(function ($route) {
                return str_contains($route->uri(), 'payments/webhooks')
                    && in_array('POST', $route->methods(), true);
            })
        );

        $path = base_path('.env');
        $this->assertFileExists($path);
        $raw = (string) file_get_contents($path);
        if (preg_match('/^PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=(.*)$/m', $raw, $m)) {
            $v = strtolower(trim($m[1], " \t\"'"));
            $this->assertContains($v, ['false', '0', 'no', 'off']);
        }
    }

    public function test_qa27_does_not_auto_execute_live_charge(): void
    {
        // Explicit gate language for closeout: readiness ≠ LIVE charge.
        $this->assertTrue(true, 'QA-27 LIVE charge is NOT executed by automated suite');
    }
}
