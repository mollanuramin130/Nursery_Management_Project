<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-36 — stability guards: status authority remains server-side.
 */
class Qa36StabilityGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pending_payment_order_detail_does_not_report_confirmed(): void
    {
        $user = User::query()->create([
            'name' => 'QA36 Cust',
            'email' => 'qa36-stability@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-QA36-PEND',
            'status' => 'PENDING_PAYMENT',
            'payment_method' => 'razorpay',
            'currency' => 'INR',
            'subtotal' => 49,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 49,
            'shipping_address_json' => [
                'name' => 'QA',
                'line1' => '1',
                'city' => 'Pune',
                'state' => 'MH',
                'postal_code' => '411001',
                'phone' => '9999999999',
                'country' => 'IN',
            ],
            'placed_at' => now(),
        ]);

        $token = JWTAuth::fromUser($user);
        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/orders/'.$order->id);

        $res->assertOk()->assertJsonPath('success', true);
        $res->assertJsonPath('data.status', 'PENDING_PAYMENT');
        $this->assertNotSame('CONFIRMED', $res->json('data.status'));
    }
}
