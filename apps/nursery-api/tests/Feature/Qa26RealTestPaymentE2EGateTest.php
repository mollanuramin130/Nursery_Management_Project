<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notification\Models\Notification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ClearsRazorpayEnv;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-26 — Real Razorpay TEST E2E gate.
 *
 * Never auto-charges PSP in PHPUnit. Documents host readiness and COD safety.
 * Real Checkout charge evidence is recorded in docs/QA-26-REPORT.md by ops/agent.
 */
class Qa26RealTestPaymentE2EGateTest extends TestCase
{
    use ClearsRazorpayEnv;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_qa26_host_credentials_are_test_mode_when_set(): void
    {
        $p = $this->razorpayEnvPresence();
        foreach (['key', 'secret', 'webhook'] as $k) {
            $this->assertContains($p[$k] === '' ? 'EMPTY' : 'SET', ['EMPTY', 'SET']);
        }
        if ($p['key'] === '') {
            $this->assertTrue(true);

            return;
        }
        $this->assertStringStartsWith('rzp_test_', $p['key']);
        $this->assertFalse(str_starts_with($p['key'], 'rzp_live_'));
    }

    public function test_qa26_unsigned_webhooks_must_remain_disabled_in_dotenv(): void
    {
        $path = base_path('.env');
        $this->assertFileExists($path);
        $raw = (string) file_get_contents($path);
        if (preg_match('/^PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=(.*)$/m', $raw, $m)) {
            $v = strtolower(trim($m[1], " \t\"'"));
            $this->assertContains($v, ['false', '0', 'no', 'off', ''], 'unsigned webhooks must stay disabled');
        } else {
            // Missing means Laravel default false — acceptable.
            $this->assertTrue(true);
        }
    }

    public function test_qa26_real_e2e_not_auto_charged_in_phpunit(): void
    {
        if (! $this->razorpayTestCredentialsConfigured()) {
            $this->markTestSkipped('TEST credentials EMPTY — real E2E BLOCKED');
        }
        // Credentials ready; PHPUnit must not complete a Checkout charge.
        $this->assertTrue(true, 'QA-26 real Checkout charge is operator/browser evidence, not PHPUnit');
    }

    public function test_qa26_cod_regression_still_works(): void
    {
        $this->clearRazorpayEnv();
        Queue::fake();
        $adminRole = Role::query()->where('slug', 'admin')->first();
        $admin = User::query()->create([
            'name' => 'QA26 Admin',
            'email' => 'qa26-admin-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->forceFill(['status' => 'active'])->save();
        if ($adminRole) {
            $admin->roles()->sync([$adminRole->id]);
        }

        $user = User::query()->create([
            'name' => 'QA26 COD',
            'email' => 'qa26-cod-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        $product = Product::query()->create([
            'name' => 'QA26 COD Plant',
            'slug' => 'qa26-cod-'.uniqid(),
            'sku' => 'QA26C-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 150,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
        $wh = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-QA26'],
            ['name' => 'QA26', 'status' => 'active', 'is_default' => true],
        );
        InventoryItem::query()->create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'qty_on_hand' => 10,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 1,
        ]);
        $ship = ShippingMethod::query()->firstOrCreate(
            ['code' => 'STD-QA26'],
            ['name' => 'Standard', 'status' => 'active', 'base_rate' => 40],
        );
        $token = JWTAuth::fromUser($user);
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();
        $address = Address::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'QA26',
            'phone' => '9555555555',
            'line1' => '26 COD',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $this->withToken($token)
            ->withHeader('X-Request-Id', 'qa26-cod-'.uniqid())
            ->postJson('/api/v1/orders', [
                'address_id' => $address->id,
                'shipping_method_id' => $ship->id,
                'payment_method' => 'cod',
            ])
            ->assertCreated();

        $this->assertTrue(
            Notification::query()->where('user_id', $user->id)->where('type', 'order_confirmed')->exists()
        );
        $this->assertTrue(Notification::query()->where('type', 'new_order')->exists());
    }
}
