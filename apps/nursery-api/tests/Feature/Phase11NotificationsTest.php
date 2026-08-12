<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationChannelsJob;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\UserDevice;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase11NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        $this->flushHeaders();
        auth()->forgetGuards();
        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
        }

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user->fresh())];
    }

    private function customer(string $email = 'notify-p11@example.com'): User
    {
        Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $user = User::query()->create([
            'name' => 'Notify Buyer',
            'email' => $email,
            'password' => Hash::make('Secret@123')
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh(['roles']);
    }

    public function test_notify_creates_in_app_and_queues_channels_idempotently(): void
    {
        Queue::fake();
        $user = $this->customer();
        /** @var NotificationService $svc */
        $svc = app(NotificationService::class);

        $first = $svc->notify($user, 'order_shipped', 'Shipped', 'On the way', [
            'order_id' => 42,
            'order_number' => 'ORD-1',
        ]);
        $second = $svc->notify($user, 'order_shipped', 'Shipped again', 'Dup', [
            'order_id' => 42,
            'order_number' => 'ORD-1',
        ]);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, Notification::query()->count());
        Queue::assertPushed(DeliverNotificationChannelsJob::class, 1);
    }

    public function test_ownership_mark_read_and_unread_count(): void
    {
        $a = $this->customer('a-p11@example.com');
        $b = $this->customer('b-p11@example.com');
        $svc = app(NotificationService::class);
        $n = $svc->notify($a, 'order_confirmed', 'Confirmed', 'Thanks', ['order_id' => 1]);

        $this->withHeaders($this->authHeader($b))
            ->postJson('/api/v1/notifications/'.$n->id.'/read')
            ->assertNotFound();

        $this->flushHeaders();
        auth()->forgetGuards();
        $this->actingAs($a->fresh(), 'api')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($a->fresh(), 'api')
            ->postJson('/api/v1/notifications/'.$n->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->actingAs($a->fresh(), 'api')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_device_register_and_deactivate(): void
    {
        $user = $this->customer('dev-p11@example.com');
        $headers = $this->authHeader($user);

        $this->withHeaders($headers)->postJson('/api/v1/devices/register', [
            'platform' => 'android',
            'device_id' => 'pixel-1',
            'push_token' => 'token-abc',
            'app_version' => '1.0.0',
        ])->assertOk();

        $this->assertTrue(UserDevice::query()->where('user_id', $user->id)->where('is_active', true)->exists());

        $this->withHeaders($headers)->postJson('/api/v1/devices/deactivate', [
            'device_id' => 'pixel-1',
        ])->assertOk();

        $this->assertFalse(UserDevice::query()->where('device_id', 'pixel-1')->value('is_active'));
    }

    public function test_channel_job_sends_email_and_records_delivery(): void
    {
        Mail::fake();
        $user = $this->customer('mail-p11@example.com');
        $n = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'order_confirmed',
            'category' => 'transactional',
            'title' => 'Confirmed',
            'body' => 'Your order is confirmed',
            'data_json' => ['order_id' => 9, 'order_number' => 'ORD-9'],
            'is_read' => false,
        ]);

        (new DeliverNotificationChannelsJob($n->id))->handle(
            app(\App\Modules\Customer\Services\PreferenceService::class),
            app(\App\Integrations\Push\FcmPushGateway::class),
        );

        Mail::assertSent(\App\Mail\CustomerNotificationMail::class);
        $this->assertTrue(
            NotificationDelivery::query()->where('notification_id', $n->id)->where('channel', 'email')->where('status', 'sent')->exists()
        );
    }

    public function test_marketing_respects_opt_out(): void
    {
        Queue::fake();
        $user = $this->customer('mkt-p11@example.com');
        $prefs = app(\App\Modules\Customer\Services\PreferenceService::class);
        $prefs->update($user, [
            'marketing_opt_in' => false,
            'notify_promotions' => false,
        ]);

        $n = app(NotificationService::class)->notify(
            $user,
            'campaign_promo',
            'Sale',
            '20% off',
            ['campaign_slug' => 'monsoon'],
            null,
            'marketing',
        );

        $this->assertNull($n);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_admin_permission_required_to_send(): void
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Permission::query()->firstOrCreate(['slug' => 'notifications.view'], ['name' => 'notifications.view']);
        Permission::query()->firstOrCreate(['slug' => 'notifications.send'], ['name' => 'notifications.send']);

        $viewer = User::query()->create([
            'name' => 'View Notif',
            'email' => 'view-notif@example.com',
            'password' => Hash::make('Secret@123')
        ]);
        $viewer->forceFill(['status' => 'active'])->save();
        $role = Role::query()->where('slug', 'admin')->first();
        $viewer->roles()->sync([$role->id]);
        $role->permissions()->sync(
            Permission::query()->where('slug', 'notifications.view')->pluck('id')->all()
        );

        $this->actingAs($viewer->fresh(['roles.permissions']), 'api')
            ->postJson('/api/v1/admin/notifications/send', [
                'user_id' => 1,
                'title' => 'Hi',
                'body' => 'Hello',
            ])
            ->assertStatus(403);
    }
}
