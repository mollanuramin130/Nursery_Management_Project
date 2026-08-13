<?php

namespace Tests\Feature;

use App\Modules\Order\Services\OrderStateMachine;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * QA-28 — lightweight consistency gates (TEST environment).
 * Does not claim LIVE payment / LIVE FCM / production readiness.
 */
class Qa28ConsistencyGateTest extends TestCase
{
    public function test_order_state_machine_exposes_expected_lifecycle_edges(): void
    {
        $fromConfirmed = OrderStateMachine::allowedFrom('CONFIRMED');
        $this->assertContains('PROCESSING', $fromConfirmed);
        $this->assertContains('CANCELLED', $fromConfirmed);

        $fromPacked = OrderStateMachine::allowedFrom('PACKED');
        $this->assertContains('SHIPPED', $fromPacked);

        $fromShipped = OrderStateMachine::allowedFrom('SHIPPED');
        $this->assertContains('OUT_FOR_DELIVERY', $fromShipped);
        $this->assertContains('DELIVERED', $fromShipped);

        $terminal = OrderStateMachine::allowedFrom('CANCELLED');
        $this->assertSame([], $terminal);
    }

    public function test_razorpay_webhook_route_is_registered(): void
    {
        $this->assertTrue(
            collect(Route::getRoutes())->contains(function ($route) {
                return str_contains($route->uri(), 'payments/webhooks');
            }),
            'POST /api/v1/payments/webhooks/{provider} must remain registered'
        );

        $response = $this->postJson('/api/v1/payments/webhooks/razorpay', []);
        $this->assertNotSame(404, $response->status());
    }
}
