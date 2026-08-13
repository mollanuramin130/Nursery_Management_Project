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
 * QA-35 — customer timeline / pending-payment copy parity.
 */
class Qa35ParityAndTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pending_payment_timeline_title_is_order_placed(): void
    {
        $user = User::query()->create([
            'name' => 'QA35 Cust',
            'email' => 'qa35-parity@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-QA35-PARITY',
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
        $timeline = $res->json('data.timeline') ?? $res->json('data.tracking.timeline');
        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);
        $first = $timeline[0];
        $this->assertSame('PENDING_PAYMENT', $first['status'] ?? null);
        $this->assertSame('Order placed', $first['title'] ?? null);
    }
}
