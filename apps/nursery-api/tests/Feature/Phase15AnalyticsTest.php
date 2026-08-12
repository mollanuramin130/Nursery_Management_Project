<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Support\SearchEventRecorder;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase15AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function analyst(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['reports.view', 'reports.export'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'P15 Analyst',
            'email' => 'p15-analyst@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', ['reports.view', 'reports.export'])->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    public function test_payments_plants_search_endpoints(): void
    {
        $staff = $this->analyst();
        $token = JWTAuth::fromUser($staff);

        $order = Order::query()->create([
            'order_number' => 'GL-P15-1',
            'user_id' => $staff->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'payment_method' => 'razorpay',
            'shipping_address_json' => [
                'name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Pune',
                'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
            ],
            'placed_at' => now(),
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $staff->id,
            'provider' => 'razorpay',
            'method' => 'online',
            'amount' => 100,
            'currency' => 'INR',
            'status' => 'success',
            'idempotency_key' => 'p15-pay-1',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/payments?preset=last_30_days')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.attempts', 1)
            ->assertJsonPath('data.summary.success', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/plants?preset=last_30_days')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview?preset=last_7_days')
            ->assertOk()
            ->assertJsonStructure(['data' => ['payment_health', 'data_gaps', 'attention']]);
    }

    public function test_search_event_recorder_and_search_analytics(): void
    {
        // Avoid hitting ProductService fulltext (unsupported on SQLite in tests).
        SearchEventRecorder::record(Request::create('/api/v1/search', 'GET'), 'snake plant', 4);
        SearchEventRecorder::record(Request::create('/api/v1/search', 'GET'), 'unicorn cactus xyz', 0);

        $this->assertDatabaseHas('search_events', [
            'normalized_query' => 'snake plant',
            'results_count' => 4,
        ]);
        $this->assertDatabaseHas('search_events', [
            'normalized_query' => 'unicorn cactus xyz',
            'results_count' => 0,
        ]);

        $staff = $this->analyst();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/search?preset=last_7_days')
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.summary.searches', 2)
            ->assertJsonPath('data.summary.zero_result_searches', 1);
    }

    public function test_orders_funnel_is_partial_not_fabricated(): void
    {
        $staff = $this->analyst();
        $token = JWTAuth::fromUser($staff);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/orders?preset=last_30_days')
            ->assertOk()
            ->json('data.funnel');

        $this->assertSame('partial', $res['supported']);
        $this->assertContains('product_view', $res['missing_stages']);
    }
}
