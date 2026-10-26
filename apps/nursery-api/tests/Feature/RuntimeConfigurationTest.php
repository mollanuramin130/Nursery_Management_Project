<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class RuntimeConfigurationTest extends TestCase
{
    public function test_configuration_survives_cache_without_runtime_environment_variables(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'nuvikoz-config-');
        unlink($cache);
        $settings = [
            'APP_CONFIG_CACHE' => $cache,
            'APP_ENV' => 'testing',
            'RAZORPAY_KEY' => 'audit-public-key',
            'RAZORPAY_SECRET' => 'audit-secret-only',
            'RAZORPAY_WEBHOOK_SECRET' => 'audit-webhook-only',
            'FREE_DELIVERY_THRESHOLD' => '1499',
            'COD_ENABLED' => 'false',
            'CUSTOMER_WEB_URL' => 'https://shop.audit.invalid',
            'ADMIN_WEB_URL' => 'https://admin.audit.invalid',
            'FIREBASE_CREDENTIALS' => '/tmp/audit-credentials.json',
        ];
        try {
            $build = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), $settings);
            $build->mustRun();
            $runtime = array_fill_keys(array_keys($settings), false);
            $runtime['APP_CONFIG_CACHE'] = $cache;
            $code = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    config('store.razorpay_key'), config('store.razorpay_secret'),
    config('store.razorpay_webhook_secret'), config('store.free_delivery_threshold'),
    config('store.cod_enabled'), config('store.customer_web_url'),
    config('store.admin_web_url'), config('services.fcm.credentials'),
]);
CODE;
            $read = new Process([PHP_BINARY, '-r', $code], base_path(), $runtime);
            $read->mustRun();
            $this->assertSame([
                'audit-public-key', 'audit-secret-only', 'audit-webhook-only', '1499', false,
                'https://shop.audit.invalid', 'https://admin.audit.invalid', '/tmp/audit-credentials.json',
            ], json_decode($read->getOutput(), true));
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
        }
    }

    public function test_app_config_uses_configuration_without_exposing_secrets(): void
    {
        config(['store.free_delivery_threshold' => 1499, 'store.cod_enabled' => false,
            'store.razorpay_key' => 'audit-key', 'store.razorpay_secret' => 'never-public']);
        $response = $this->getJson('/api/v1/app/config')->assertOk()
            ->assertJsonPath('data.commerce.free_delivery_threshold', 1499)
            ->assertJsonPath('data.feature_flags.cod_enabled', false);
        $this->assertStringNotContainsString('never-public', $response->getContent());
    }
}
