<?php

namespace Tests\Feature;

use App\Integrations\Payment\RazorpayGateway;
use App\Shared\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;

class Qa04PaymentStubGuardTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    public function test_production_refuses_local_stub_order_create(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->clearRazorpayEnv();

        $gateway = new RazorpayGateway;

        try {
            $gateway->createOrder(100, 'INR', 'ORD-TEST-1');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('PAYMENT_GATEWAY_UNAVAILABLE', $e->errorCode());
        }
    }

    public function test_production_rejects_local_stub_signature(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->clearRazorpayEnv();

        $gateway = new RazorpayGateway;
        $this->assertFalse(
            $gateway->verifySignature('order_local_abc', 'local_pay_1', 'local_order_local_abc'),
        );
    }

    public function test_local_allows_stub_when_keys_empty(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->clearRazorpayEnv();

        $gateway = new RazorpayGateway;
        $created = $gateway->createOrder(50, 'INR', 'ORD-LOCAL-1');
        $this->assertSame('local_stub', $created['client_payload']['mode'] ?? null);
        $this->assertTrue(
            $gateway->verifySignature(
                $created['provider_order_id'],
                'local_1',
                'local_'.$created['provider_order_id'],
            ),
        );
    }
}
