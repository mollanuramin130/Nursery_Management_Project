<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Address;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionCycle;
use App\Modules\Subscription\Models\SubscriptionPlan;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase10SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        $this->flushHeaders();
        auth()->forgetGuards();
        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
            // ignore
        }

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user->fresh())];
    }

    private function staff(array $perms): User
    {
        Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Sub Ops',
            'email' => 'sub-ops@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'super_admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')->all()
        );

        return $user->fresh(['roles.permissions']);
    }

    private function customer(string $email = 'buyer-p10@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'Buyer Ten',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh(['roles']);
    }

    /**
     * @return array{customer: User, product: Product, plan: SubscriptionPlan, address: Address, shipping: ShippingMethod}
     */
    private function seedCatalog(): array
    {
        $customer = $this->customer();
        $warehouse = Warehouse::query()->create([
            'code' => 'P10',
            'name' => 'P10 WH',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'fertilizer',
            'name' => 'Monthly Care Kit',
            'slug' => 'monthly-care-'.uniqid(),
            'sku' => 'KIT-'.uniqid(),
            'price' => 499,
            'currency' => 'INR',
            'status' => 'active',
            'stock_status' => 'in_stock',
        ]);
        InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 100,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 2,
        ]);
        $plan = SubscriptionPlan::query()->create([
            'product_id' => $product->id,
            'name' => 'Monthly Care',
            'slug' => 'monthly-care-plan-'.uniqid(),
            'frequency' => 'MONTHLY',
            'quantity_default' => 1,
            'unit_price' => 449,
            'currency' => 'INR',
            'status' => 'active',
        ]);
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'name' => 'Buyer',
            'phone' => '9999999999',
            'line1' => 'Street 1',
            'city' => 'Pune',
            'state' => 'MH',
            'postal_code' => '411001',
            'country' => 'IN',
            'is_default' => true,
        ]);
        $shipping = ShippingMethod::query()->create([
            'code' => 'STD',
            'name' => 'Standard',
            'price' => 49,
            'currency' => 'INR',
            'eta_min_days' => 2,
            'eta_max_days' => 5,
            'status' => 'active',
        ]);

        return compact('customer', 'product', 'plan', 'address', 'shipping');
    }

    public function test_create_subscription_cod_and_duplicate_cycle_prevention(): void
    {
        $seed = $this->seedCatalog();
        $headers = $this->authHeader($seed['customer']);

        $res = $this->withHeaders($headers)->postJson('/api/v1/subscriptions', [
            'plan_id' => $seed['plan']->id,
            'quantity' => 2,
            'address_id' => $seed['address']->id,
            'shipping_method_id' => $seed['shipping']->id,
            'payment_method' => 'cod',
        ]);
        $res->assertCreated();
        $res->assertJsonPath('data.subscription.status', 'ACTIVE');
        $res->assertJsonPath('data.order.status', 'CONFIRMED');
        $res->assertJsonPath('data.billing_model', 'pay_per_cycle');

        $subId = (int) $res->json('data.subscription.id');
        $this->assertSame(1, SubscriptionCycle::query()->where('subscription_id', $subId)->count());
        $this->assertNotNull(Order::query()->where('subscription_id', $subId)->value('id'));

        // Force due and process twice — still one extra cycle max once.
        $sub = Subscription::query()->findOrFail($subId);
        $sub->next_billing_at = now()->subMinute();
        $sub->save();

        /** @var SubscriptionService $svc */
        $svc = app(SubscriptionService::class);
        $first = $svc->processOne($subId);
        $this->assertNotNull($first['order'] ?? null);
        $this->assertSame(2, SubscriptionCycle::query()->where('subscription_id', $subId)->count());

        // Second run immediately should skip (not due / open unpaid cycle).
        try {
            $svc->processOne($subId);
            $this->fail('Expected skip on duplicate processing');
        } catch (\App\Shared\Exceptions\ApiException $e) {
            $this->assertSame('SKIPPED', $e->errorCode());
        }
        $this->assertSame(2, SubscriptionCycle::query()->where('subscription_id', $subId)->count());
    }

    public function test_inactive_plan_and_invalid_quantity_rejected(): void
    {
        $seed = $this->seedCatalog();
        $seed['plan']->update(['status' => 'draft']);
        $headers = $this->authHeader($seed['customer']);

        $this->withHeaders($headers)->postJson('/api/v1/subscriptions', [
            'plan_id' => $seed['plan']->id,
            'address_id' => $seed['address']->id,
            'payment_method' => 'cod',
        ])->assertStatus(400);

        $seed['plan']->update(['status' => 'active']);
        $this->withHeaders($headers)->postJson('/api/v1/subscriptions', [
            'plan_id' => $seed['plan']->id,
            'quantity' => 0,
            'address_id' => $seed['address']->id,
            'payment_method' => 'cod',
        ])->assertStatus(422);
    }

    public function test_pause_resume_cancel_and_ownership(): void
    {
        $seed = $this->seedCatalog();
        $headers = $this->authHeader($seed['customer']);
        $created = $this->withHeaders($headers)->postJson('/api/v1/subscriptions', [
            'plan_id' => $seed['plan']->id,
            'address_id' => $seed['address']->id,
            'shipping_method_id' => $seed['shipping']->id,
            'payment_method' => 'cod',
        ])->json('data.subscription');

        $id = (int) $created['id'];
        $this->withHeaders($headers)->postJson("/api/v1/subscriptions/{$id}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAUSED');

        $this->withHeaders($headers)->postJson("/api/v1/subscriptions/{$id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->withHeaders($headers)->postJson("/api/v1/subscriptions/{$id}/cancel", ['reason' => 'Too many plants'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $other = $this->customer('other-p10@example.com');
        $list = app(SubscriptionService::class)->listCustomer($other, null, 20);
        $this->assertSame(0, count($list['data']));
    }

    public function test_admin_permissions_and_plans(): void
    {
        $seed = $this->seedCatalog();
        $staff = $this->staff(['subscriptions.view', 'subscriptions.manage']);
        $headers = $this->authHeader($staff);

        $this->withHeaders($headers)->getJson('/api/v1/admin/subscriptions/dashboard')->assertOk();
        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/subscription-plans', [
            'product_id' => $seed['product']->id,
            'name' => 'Quarterly Soil',
            'frequency' => 'QUARTERLY',
            'unit_price' => 999,
            'status' => 'active',
        ]);
        $created->assertCreated();

        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Permission::query()->firstOrCreate(['slug' => 'subscriptions.view'], ['name' => 'subscriptions.view']);
        Permission::query()->firstOrCreate(['slug' => 'subscriptions.manage'], ['name' => 'subscriptions.manage']);
        $viewer = User::query()->create([
            'name' => 'View Only Sub',
            'email' => 'sub-view-only@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $viewer->forceFill(['status' => 'active'])->save();
        $viewer->roles()->sync([$adminRole->id]);
        $adminRole->permissions()->sync(
            Permission::query()->where('slug', 'subscriptions.view')->pluck('id')->all()
        );

        $this->flushHeaders();
        auth()->forgetGuards();
        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
        }

        $this->actingAs($viewer->fresh(['roles.permissions']), 'api')
            ->postJson('/api/v1/admin/subscription-plans', [
                'product_id' => $seed['product']->id,
                'name' => 'Should Fail',
                'frequency' => 'MONTHLY',
                'unit_price' => 100,
                'status' => 'active',
            ])
            ->assertStatus(403);
    }

    public function test_product_detail_exposes_plans(): void
    {
        $seed = $this->seedCatalog();
        $this->getJson('/api/v1/products/'.$seed['product']->slug)
            ->assertOk()
            ->assertJsonPath('data.subscription_enabled', true)
            ->assertJsonPath('data.subscription_plans.0.unit_price', 449);
    }

    public function test_out_of_stock_skips_cycle_without_order(): void
    {
        $seed = $this->seedCatalog();
        $headers = $this->authHeader($seed['customer']);
        $created = $this->withHeaders($headers)->postJson('/api/v1/subscriptions', [
            'plan_id' => $seed['plan']->id,
            'address_id' => $seed['address']->id,
            'payment_method' => 'cod',
        ])->json('data.subscription');
        $subId = (int) $created['id'];

        InventoryItem::query()->where('product_id', $seed['product']->id)->update([
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
        ]);
        Product::query()->whereKey($seed['product']->id)->update(['stock_status' => 'out_of_stock']);

        $sub = Subscription::query()->findOrFail($subId);
        $sub->next_billing_at = now()->subMinute();
        $sub->save();

        $result = app(SubscriptionService::class)->processOne($subId);
        $this->assertTrue($result['skipped'] ?? false);
        $this->assertNull($result['order']);
        $this->assertSame('SKIPPED', $result['cycle']['status']);
    }
}
