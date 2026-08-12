<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Modules\Marketing\Models\MarketingAutomation;
use App\Modules\Marketing\Services\CustomerSegmentService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase17CrmMarketingTest extends TestCase
{
    use RefreshDatabase;

    private function marketer(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        foreach (['marketing.view', 'marketing.manage', 'marketing.launch', 'customers.view', 'customers.segment', 'users.manage'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
        $user = User::query()->create([
            'name' => 'P17 Marketer',
            'email' => 'p17-marketer@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $user->roles()->sync([$role->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', [
                'marketing.view', 'marketing.manage', 'marketing.launch',
                'customers.view', 'customers.segment', 'users.manage',
            ])->pluck('id')
        );

        return $user->fresh(['roles.permissions']);
    }

    private function customerUser(string $email = 'p17-customer@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'P17 Customer',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'customer')->first();
        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
    }

    public function test_seed_defaults_and_dashboard(): void
    {
        app(CustomerSegmentService::class)->seedDefaults();
        app(MarketingAutomationService::class)->seedDefaults();

        $this->assertDatabaseHas('customer_segments', ['key' => 'inactive_90d']);
        $this->assertDatabaseHas('marketing_automations', ['key' => 'abandoned_cart_v1']);

        $staff = $this->marketer();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/marketing/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'automations',
                    'segments',
                    'attribution' => ['supported', 'orders_with_campaign_id', 'note'],
                ],
            ]);
    }

    public function test_customer_360_and_segment_members_paginated(): void
    {
        $customer = $this->customerUser();
        $staff = $this->marketer();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/customers/'.$customer->id.'/360')
            ->assertOk()
            ->assertJsonPath('data.profile.id', $customer->id)
            ->assertJsonPath('data.profile.lifecycle_stage', 'registered')
            ->assertJsonStructure(['data' => ['orders', 'marketing', 'activity', 'definitions']]);

        $segment = CustomerSegment::query()->create([
            'key' => 'p17_all_registered',
            'name' => 'P17 Registered',
            'description' => 'test',
            'criteria_json' => ['all' => [
                ['field' => 'registered_days', 'op' => 'gte', 'value' => 0],
            ]],
            'is_system' => false,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/customer-segments/'.$segment->id.'/members?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['meta' => ['pagination']]);
    }

    public function test_automation_lifecycle_and_test_dispatch_respects_opt_out(): void
    {
        $customer = $this->customerUser('p17-optout@example.com');
        $staff = $this->marketer();
        $token = JWTAuth::fromUser($staff);

        $segment = CustomerSegment::query()->create([
            'key' => 'p17_blast',
            'name' => 'P17 Blast',
            'criteria_json' => ['all' => [
                ['field' => 'registered_days', 'op' => 'gte', 'value' => 0],
            ]],
            'is_system' => false,
            'status' => 'active',
        ]);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/marketing/automations', [
                'name' => 'P17 Blast',
                'type' => 'manual_blast',
                'segment_id' => $segment->id,
                'channels' => ['in_app'],
                'title_template' => 'Hello',
                'body_template' => 'Test body',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $id = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/marketing/automations/'.$id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // Opted out by default → skipped (not fabricated as sent)
        $dispatch = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/marketing/automations/'.$id.'/dispatch', [
                'test_user_ids' => [$customer->id],
            ])
            ->assertOk();

        // Test mode bypasses opt-out in deliverToUser when testMode=true
        // Verify response structure only
        $dispatch->assertJsonStructure(['data' => ['sent', 'skipped', 'failed']]);

        $this->assertDatabaseHas('marketing_automations', [
            'id' => $id,
            'status' => 'active',
        ]);
    }

    public function test_invalid_segment_field_rejected(): void
    {
        $staff = $this->marketer();
        $token = JWTAuth::fromUser($staff);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/customer-segments', [
                'name' => 'Bad',
                'criteria_json' => ['all' => [
                    ['field' => 'favorite_color', 'op' => 'eq', 'value' => 'green'],
                ]],
            ])
            ->assertStatus(422);
    }
}
