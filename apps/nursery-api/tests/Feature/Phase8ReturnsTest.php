<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\ReturnRequest;
use App\Modules\Order\Models\Shipment;
use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase8ReturnsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms, string $email = 'returns-ops@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        foreach ($perms as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'Returns Ops',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        // Mirror Phase 9/10/11: super_admin bypasses permission middleware in tests.
        $role = Role::query()->where('slug', 'super_admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $perms)->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    private function customer(string $email = 'buyer-r8@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'Buyer',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh(['roles']);
    }

    /**
     * @return array{order: Order, item: OrderItem, warehouse: Warehouse, product: Product, customer: User, inventory: InventoryItem}
     */
    private function seedDeliveredOrder(int $qty = 5, ?User $customer = null): array
    {
        $customer ??= $this->customer();
        $warehouse = Warehouse::query()->create([
            'code' => 'MAIN-R8',
            'name' => 'Main R8',
            'is_default' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'product_type' => 'plant',
            'name' => 'Snake Plant R8',
            'slug' => 'snake-r8-'.uniqid(),
            'sku' => 'SP-R8-'.uniqid(),
            'price' => 100,
            'currency' => 'INR',
            'status' => 'active',
        ]);
        $inventory = InventoryItem::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => 50,
            'qty_reserved' => 0,
            'qty_damaged' => 0,
            'low_stock_threshold' => 5,
        ]);

        $addr = [
            'name' => 'Buyer', 'phone' => '9999999999', 'line1' => 'Street 1',
            'city' => 'Pune', 'state' => 'MH', 'postal_code' => '411001', 'country' => 'IN',
        ];
        $order = Order::query()->create([
            'order_number' => 'GL-R8-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'DELIVERED',
            'currency' => 'INR',
            'subtotal' => 100 * $qty,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100 * $qty,
            'payment_method' => 'online',
            'warehouse_id' => $warehouse->id,
            'shipping_address_json' => $addr,
            'billing_address_json' => $addr,
            'confirmed_at' => now()->subDays(3),
            'placed_at' => now()->subDays(3),
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => 100,
            'quantity' => $qty,
            'line_total' => 100 * $qty,
        ]);
        Shipment::query()->create([
            'order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'DELIVERED',
            'carrier' => 'internal',
            'tracking_number' => 'TRK-R8-'.uniqid(),
            'shipped_at' => now()->subDays(2),
            'delivered_at' => now()->subDay(),
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'razorpay',
            'method' => 'online',
            'amount' => 100 * $qty,
            'currency' => 'INR',
            'status' => 'success',
            'idempotency_key' => 'phase8-pay-'.$order->id.'-'.uniqid(),
            'paid_at' => now()->subDays(3),
        ]);

        return compact('order', 'item', 'warehouse', 'product', 'customer', 'inventory');
    }

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

    private function actingAsApi(User $user): static
    {
        $this->flushHeaders();
        auth()->forgetGuards();
        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
            // ignore
        }

        return $this->actingAs($user->fresh(['roles.permissions']), 'api');
    }

    public function test_eligible_delivered_order_can_request_return(): void
    {
        $seed = $this->seedDeliveredOrder(3);
        $token = $this->authHeader($seed['customer']);

        $this->withHeaders($token)
            ->getJson('/api/v1/orders/'.$seed['order']->id)
            ->assertOk()
            ->assertJsonPath('data.can_return', true);

        $this->withHeaders($token)
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'damaged',
                ]],
                'notes' => 'Leaf damage',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $seed['order']->id,
            'status' => 'RETURN_REQUESTED',
        ]);
        $this->assertSame('RETURN_REQUESTED', $seed['order']->fresh()->status);
    }

    public function test_ineligible_non_delivered_order_rejected(): void
    {
        $seed = $this->seedDeliveredOrder(2);
        $seed['order']->update(['status' => 'CONFIRMED']);

        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'changed_mind',
                ]],
            ])
            ->assertStatus(409);
    }

    public function test_quantity_validation_rejects_over_returnable(): void
    {
        $seed = $this->seedDeliveredOrder(5);
        $token = $this->authHeader($seed['customer']);

        $this->withHeaders($token)
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 2,
                    'reason' => 'wrong_item',
                ]],
            ])
            ->assertCreated();

        // Open return blocks a second open request entirely.
        $this->withHeaders($token)
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 4,
                    'reason' => 'wrong_item',
                ]],
            ])
            ->assertStatus(409);

        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ]);
        $returnId = ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/reject", ['reason' => 'Incomplete photos'])
            ->assertOk();

        // After reject, returnable is still 5; attempt 6 fails qty validation.
        $this->actingAsApi($seed['customer'])
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 6,
                    'reason' => 'damaged',
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_customer_cannot_view_another_customers_return(): void
    {
        $seed = $this->seedDeliveredOrder(1);
        $other = $this->customer('other-r8@example.com');

        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'other',
                ]],
            ])
            ->assertCreated();

        $returnId = ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');

        $this->flushHeaders();
        auth()->forgetGuards();
        $this->actingAs($other->fresh(), 'api')
            ->getJson("/api/v1/returns/{$returnId}")
            ->assertNotFound();
    }

    public function test_admin_approve_and_invalid_transition(): void
    {
        $seed = $this->seedDeliveredOrder(1);
        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'damaged',
                ]],
            ])
            ->assertCreated();

        $returnId = (int) ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');
        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ]);
        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        // COMPLETED cannot go to APPROVED — force status then attempt.
        ReturnRequest::query()->whereKey($returnId)->update(['status' => 'COMPLETED']);
        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/approve")
            ->assertStatus(409);
    }

    public function test_receive_inspect_inventory_and_refund_idempotency(): void
    {
        $seed = $this->seedDeliveredOrder(3);
        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 2,
                    'reason' => 'damaged',
                ]],
            ])
            ->assertCreated();

        $returnId = (int) ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');
        $returnItemId = (int) \App\Modules\Order\Models\ReturnItem::query()
            ->where('return_request_id', $returnId)
            ->value('id');

        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ]);
        $this->actingAsApi($admin)->postJson("/api/v1/admin/returns/{$returnId}/approve")->assertOk();
        $this->actingAsApi($admin)->postJson("/api/v1/admin/returns/{$returnId}/schedule-pickup")->assertOk();
        $this->actingAsApi($admin)->postJson("/api/v1/admin/returns/{$returnId}/picked-up")->assertOk();

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/receive", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'received_qty' => 2,
                    'condition' => 'GOOD',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'RECEIVED');

        $onHandBefore = (int) $seed['inventory']->fresh()->qty_on_hand;
        $damagedBefore = (int) $seed['inventory']->fresh()->qty_damaged;

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/inspect", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'accepted_qty' => 1,
                    'rejected_qty' => 1,
                    'disposition' => 'SELLABLE',
                ]],
                'create_refund' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');

        $inv = $seed['inventory']->fresh();
        $this->assertSame($onHandBefore + 1, (int) $inv->qty_on_hand);
        $this->assertSame($damagedBefore, (int) $inv->qty_damaged);

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/refund", [
                'amount' => 100,
                'idempotency_key' => 'r8-refund-1',
                'reason' => 'Partial return refund',
            ])
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', false);

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/refund", [
                'amount' => 100,
                'idempotency_key' => 'r8-refund-1',
                'reason' => 'Partial return refund',
            ])
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, \App\Modules\Admin\Models\Refund::query()->where('order_id', $seed['order']->id)->count());
    }

    public function test_damaged_disposition_does_not_increase_sellable(): void
    {
        $seed = $this->seedDeliveredOrder(2);
        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 2,
                    'reason' => 'damaged',
                ]],
            ])
            ->assertCreated();

        $returnId = (int) ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');
        $returnItemId = (int) \App\Modules\Order\Models\ReturnItem::query()
            ->where('return_request_id', $returnId)
            ->value('id');

        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ], 'returns-ops-2@example.com');
        $this->actingAsApi($admin)->postJson("/api/v1/admin/returns/{$returnId}/approve")->assertOk();
        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/receive", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'received_qty' => 2,
                    'condition' => 'DAMAGED',
                ]],
            ])
            ->assertOk();

        $before = $seed['inventory']->fresh();
        $sellableBefore = (int) $before->qty_on_hand - (int) $before->qty_reserved - (int) $before->qty_damaged;

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/inspect", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'accepted_qty' => 2,
                    'rejected_qty' => 0,
                    'disposition' => 'DAMAGED',
                ]],
            ])
            ->assertOk();

        $after = $seed['inventory']->fresh();
        $sellableAfter = (int) $after->qty_on_hand - (int) $after->qty_reserved - (int) $after->qty_damaged;
        $this->assertSame($sellableBefore, $sellableAfter);
        $this->assertSame((int) $before->qty_damaged + 2, (int) $after->qty_damaged);
    }

    public function test_refund_cap_rejects_over_paid_amount(): void
    {
        $seed = $this->seedDeliveredOrder(1);
        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'changed_mind',
                ]],
            ])
            ->assertCreated();

        $returnId = (int) ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');
        $returnItemId = (int) \App\Modules\Order\Models\ReturnItem::query()
            ->where('return_request_id', $returnId)
            ->value('id');

        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ], 'returns-ops-3@example.com');
        $this->actingAsApi($admin)->postJson("/api/v1/admin/returns/{$returnId}/approve")->assertOk();
        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/receive", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'received_qty' => 1,
                    'condition' => 'GOOD',
                ]],
            ])
            ->assertOk();
        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/inspect", [
                'items' => [[
                    'return_item_id' => $returnItemId,
                    'accepted_qty' => 1,
                    'rejected_qty' => 0,
                    'disposition' => 'SELLABLE',
                ]],
            ])
            ->assertOk();

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/refund", [
                'amount' => 1500,
                'idempotency_key' => 'over-cap',
            ])
            ->assertStatus(422);
    }

    public function test_customer_serialize_hides_admin_meta(): void
    {
        $seed = $this->seedDeliveredOrder(1);
        $this->withHeaders($this->authHeader($seed['customer']))
            ->postJson('/api/v1/orders/'.$seed['order']->id.'/returns', [
                'items' => [[
                    'order_item_id' => $seed['item']->id,
                    'quantity' => 1,
                    'reason' => 'other',
                ]],
                'notes' => 'Customer note',
            ])
            ->assertCreated();

        $returnId = (int) ReturnRequest::query()->where('order_id', $seed['order']->id)->value('id');
        $admin = $this->staff([
            'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage', 'payments.refund',
        ], 'returns-ops-4@example.com');

        $this->actingAsApi($admin)
            ->postJson("/api/v1/admin/returns/{$returnId}/approve", ['note' => 'internal ok'])
            ->assertOk();

        $customerView = $this->actingAsApi($seed['customer'])
            ->getJson("/api/v1/returns/{$returnId}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('meta', $customerView);
        $this->assertArrayNotHasKey('actions', $customerView);
        $this->assertArrayNotHasKey('inspection', $customerView);
        $this->assertArrayNotHasKey('reverse_shipment', $customerView);
        $this->assertSame('APPROVED', $customerView['status']);
    }
}
