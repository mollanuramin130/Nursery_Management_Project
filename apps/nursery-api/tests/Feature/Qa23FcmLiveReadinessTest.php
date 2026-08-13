<?php

namespace Tests\Feature;

use App\Integrations\Push\FcmPushGateway;
use App\Jobs\DeliverNotificationChannelsJob;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\UserDevice;
use App\Modules\Notification\Services\NotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * QA-23 FCM readiness — does NOT claim real-device LIVE push.
 */
class Qa23FcmLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        putenv('FCM_SERVER_KEY=');
        putenv('FIREBASE_CREDENTIALS=');
        $_ENV['FCM_SERVER_KEY'] = '';
        $_ENV['FIREBASE_CREDENTIALS'] = '';
    }

    public function test_gateway_mode_is_local_stub_without_credentials(): void
    {
        $gateway = app(FcmPushGateway::class);
        $this->assertSame('local_stub', $gateway->mode());
        $result = $gateway->send('tok', 'Title', 'Body', ['order_id' => '1']);
        $this->assertTrue($result['ok']);
        $this->assertSame('local_stub', $result['mode']);
    }

    public function test_legacy_server_key_path_marks_invalid_token(): void
    {
        putenv('FCM_SERVER_KEY=test-server-key-not-real');
        $_ENV['FCM_SERVER_KEY'] = 'test-server-key-not-real';

        Http::fake([
            'https://fcm.googleapis.com/fcm/send' => Http::response([
                'multicast_id' => 1,
                'success' => 0,
                'failure' => 1,
                'results' => [['error' => 'NotRegistered']],
            ], 200),
        ]);

        $result = app(FcmPushGateway::class)->send('bad-token', 'T', 'B', []);
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['invalid_token']);
        $this->assertSame('legacy_server_key', $result['mode']);
    }

    public function test_http_v1_uses_service_account_when_credentials_file_present(): void
    {
        $path = storage_path('app/qa23-fake-sa.json');
        // Minimal RSA key for openssl (test-only, not a cloud credential).
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $pem);
        file_put_contents($path, json_encode([
            'project_id' => 'greenleaf-qa23-test',
            'client_email' => 'firebase-adminsdk@greenleaf-qa23-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));

        putenv('FIREBASE_CREDENTIALS='.$path);
        $_ENV['FIREBASE_CREDENTIALS'] = $path;

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.fake-access',
                'expires_in' => 3600,
            ], 200),
            'https://fcm.googleapis.com/v1/projects/greenleaf-qa23-test/messages:send' => Http::response([
                'name' => 'projects/greenleaf-qa23-test/messages/0:1',
            ], 200),
        ]);

        $gateway = app(FcmPushGateway::class);
        $this->assertSame('http_v1', $gateway->mode());
        $result = $gateway->send('device-token', 'New Order', 'ORD-1 placed', [
            'type' => 'new_order',
            'route' => '/orders/1',
            'order_id' => '1',
        ]);
        $this->assertTrue($result['ok']);
        $this->assertSame('http_v1', $result['mode']);
        $this->assertFalse($result['invalid_token']);

        @unlink($path);
        putenv('FIREBASE_CREDENTIALS=');
        $_ENV['FIREBASE_CREDENTIALS'] = '';
    }

    public function test_http_v1_unregistered_token_flagged(): void
    {
        $path = storage_path('app/qa23-fake-sa-unreg.json');
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $pem);
        file_put_contents($path, json_encode([
            'project_id' => 'greenleaf-qa23-test',
            'client_email' => 'firebase-adminsdk@greenleaf-qa23-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));
        putenv('FIREBASE_CREDENTIALS='.$path);
        $_ENV['FIREBASE_CREDENTIALS'] = $path;

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake', 'expires_in' => 3600], 200),
            'https://fcm.googleapis.com/v1/projects/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [['errorCode' => 'UNREGISTERED']],
                ],
            ], 404),
        ]);

        $result = app(FcmPushGateway::class)->send('stale', 'T', 'B', []);
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['invalid_token']);

        @unlink($path);
        putenv('FIREBASE_CREDENTIALS=');
        $_ENV['FIREBASE_CREDENTIALS'] = '';
    }

    public function test_delivery_job_deactivates_invalid_token(): void
    {
        putenv('FCM_SERVER_KEY=test-server-key-not-real');
        $_ENV['FCM_SERVER_KEY'] = 'test-server-key-not-real';
        $_SERVER['FCM_SERVER_KEY'] = 'test-server-key-not-real';
        putenv('FIREBASE_CREDENTIALS=');
        $_ENV['FIREBASE_CREDENTIALS'] = '';
        $_SERVER['FIREBASE_CREDENTIALS'] = '';

        Http::fake([
            'https://fcm.googleapis.com/fcm/send' => Http::response([
                'success' => 0,
                'failure' => 1,
                'results' => [['error' => 'InvalidRegistration']],
            ], 200),
        ]);

        $user = User::query()->create([
            'name' => 'FCM User',
            'email' => 'fcm-qa23-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();

        UserDevice::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'device_id' => 'dev-bad',
            'push_token' => 'invalid-token',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $n = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'order_packed',
            'category' => 'transactional',
            'title' => 'Packed',
            'body' => 'Your order is packed',
            'data_json' => ['order_id' => 9, 'route' => '/account/orders/9', 'type' => 'order_packed'],
            'is_read' => false,
            'idempotency_key' => 'qa23-fcm-invalid-'.uniqid(),
        ]);

        try {
            (new DeliverNotificationChannelsJob($n->id))->handle(
                app(PreferenceService::class),
                app(FcmPushGateway::class),
            );
        } catch (\Throwable) {
            // push failure may rethrow for queue retry
        }

        $this->assertFalse((bool) UserDevice::query()->where('user_id', $user->id)->value('is_active'));
    }

    public function test_notification_payload_has_no_secrets(): void
    {
        $user = User::query()->create([
            'name' => 'Sec',
            'email' => 'fcm-sec-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();

        $n = app(NotificationService::class)->notify(
            $user,
            'new_order',
            'New Order',
            'Order placed',
            [
                'type' => 'new_order',
                'order_id' => 3,
                'route' => '/orders/3',
                'audience' => 'admin',
            ],
        );
        $encoded = json_encode($n?->data_json);
        $this->assertStringNotContainsString('firebase', strtolower((string) $encoded));
        $this->assertStringNotContainsString('private_key', strtolower((string) $encoded));
        $this->assertStringNotContainsString('fcm_server', strtolower((string) $encoded));
    }
}
