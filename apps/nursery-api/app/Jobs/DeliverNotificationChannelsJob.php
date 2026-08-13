<?php

namespace App\Jobs;

use App\Integrations\Push\FcmPushGateway;
use App\Mail\CustomerNotificationMail;
use App\Modules\Auth\Models\User;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Models\UserDevice;
use App\Modules\Notification\Support\NotificationChannelMap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverNotificationChannelsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId) {}

    public function handle(PreferenceService $prefs, FcmPushGateway $fcm): void
    {
        $notification = Notification::query()->find($this->notificationId);
        if (! $notification) {
            return;
        }

        $user = User::query()->find($notification->user_id);
        if (! $user) {
            return;
        }

        $config = NotificationChannelMap::forType($notification->type);
        $channels = $config['channels'];
        $preferences = $prefs->get($user);
        $data = is_array($notification->data_json) ? $notification->data_json : [];

        foreach ($channels as $channel) {
            if ($channel === 'in_app') {
                $this->markChannel($notification->id, 'in_app', 'sent', 'database');
                continue;
            }

            if ($channel === 'email') {
                if (! $this->allowEmail($notification, $preferences)) {
                    $this->markChannel($notification->id, 'email', 'skipped', null, 'preference');
                    continue;
                }
                $this->sendEmail($notification, $user, $data);
                continue;
            }

            if ($channel === 'push') {
                if (! $this->allowPush($notification, $preferences)) {
                    $this->markChannel($notification->id, 'push', 'skipped', null, 'preference');
                    continue;
                }
                $this->sendPush($notification, $user, $data, $fcm);
            }
        }
    }

    private function allowEmail(Notification $n, array $preferences): bool
    {
        if (! ($preferences['notify_email'] ?? true)) {
            // Transactional emails still send when notify_email is false? Spec says transactional
            // must not be disabled by marketing opt-out. notify_email is a channel toggle —
            // if customer disables email channel entirely, skip. Marketing also needs opt-in.
            if (($n->category ?? 'transactional') === 'transactional') {
                // Keep transactional email even if notify_email false? Spec: separate transactional from marketing.
                // Interpret notify_email as marketing+optional; transactional always emails if address exists.
                return true;
            }

            return false;
        }
        if (($n->category ?? 'transactional') === 'marketing') {
            return (bool) ($preferences['marketing_opt_in'] ?? false)
                && (bool) ($preferences['notify_promotions'] ?? false);
        }

        return true;
    }

    private function allowPush(Notification $n, array $preferences): bool
    {
        if (! ($preferences['notify_push'] ?? true)) {
            return false;
        }
        if (($n->category ?? 'transactional') === 'marketing') {
            return (bool) ($preferences['marketing_opt_in'] ?? false)
                && (bool) ($preferences['notify_promotions'] ?? false);
        }

        return true;
    }

    private function sendEmail(Notification $notification, User $user, array $data): void
    {
        $delivery = $this->claimChannel($notification->id, 'email', 'smtp');
        if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        try {
            $template = NotificationTemplate::query()
                ->where('code', strtolower($notification->type))
                ->where('channel', 'email')
                ->where('status', 'active')
                ->first();

            $subject = $template?->subject ?: $notification->title;
            $body = $template?->body
                ? $this->interpolate($template->body, $user, $data, $notification)
                : $notification->body;
            $subject = $this->interpolate($subject, $user, $data, $notification);

            $cta = $this->safeCta($data);

            Mail::to($user->email)->send(new CustomerNotificationMail(
                customerName: $user->name ?: 'Gardener',
                emailSubject: $subject,
                headline: $notification->title,
                bodyText: strip_tags($body),
                ctaLabel: $cta['label'],
                ctaUrl: $cta['url'],
            ));

            $delivery->status = 'sent';
            $delivery->provider = app()->environment('production') && env('MAIL_MAILER') === 'log' ? 'log' : (string) config('mail.default');
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->sent_at = now();
            $delivery->error_message = null;
            $delivery->save();
        } catch (Throwable $e) {
            $delivery->status = 'failed';
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->error_message = substr($e->getMessage(), 0, 500);
            $delivery->save();
            throw $e; // allow queue retry
        }
    }

    private function sendPush(Notification $notification, User $user, array $data, FcmPushGateway $fcm): void
    {
        $delivery = $this->claimChannel($notification->id, 'push', 'fcm');
        if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        $devices = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->get();

        if ($devices->isEmpty()) {
            $delivery->status = 'skipped';
            $delivery->error_message = 'no_active_tokens';
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->save();

            return;
        }

        $anyOk = false;
        $errors = [];
        foreach ($devices as $device) {
            $result = $fcm->send(
                (string) $device->push_token,
                $notification->title,
                $notification->body,
                array_merge($data, [
                    'notification_id' => $notification->id,
                    'type' => $notification->type,
                ]),
            );
            if ($result['ok'] ?? false) {
                $anyOk = true;
            } else {
                $errors[] = $result['error'] ?? 'send_failed';
            }
            if ($result['invalid_token'] ?? false) {
                $device->is_active = false;
                $device->save();
            }
        }

        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->provider = 'fcm';
        if ($anyOk) {
            $delivery->status = 'sent';
            $delivery->sent_at = now();
            $delivery->error_message = null;
            $delivery->save();
        } else {
            $delivery->status = 'failed';
            $delivery->error_message = substr(implode('; ', $errors) ?: 'all_devices_failed', 0, 500);
            $delivery->save();
            throw new \RuntimeException('Push delivery failed for notification '.$notification->id);
        }
    }

    private function claimChannel(int $notificationId, string $channel, ?string $provider): ?NotificationDelivery
    {
        $existing = NotificationDelivery::query()
            ->where('notification_id', $notificationId)
            ->where('channel', $channel)
            ->first();
        if ($existing) {
            return $existing;
        }

        return NotificationDelivery::query()->create([
            'notification_id' => $notificationId,
            'channel' => $channel,
            'status' => 'queued',
            'provider' => $provider,
            'attempts' => 0,
        ]);
    }

    private function markChannel(int $notificationId, string $channel, string $status, ?string $provider, ?string $reason = null): void
    {
        $row = $this->claimChannel($notificationId, $channel, $provider);
        if (! $row) {
            return;
        }
        if (in_array($row->status, ['sent'], true) && $status !== 'sent') {
            return;
        }
        $row->status = $status;
        $row->provider = $provider ?? $row->provider;
        $row->error_message = $reason;
        if ($status === 'sent') {
            $row->sent_at = now();
        }
        $row->save();
    }

    private function interpolate(string $text, User $user, array $data, Notification $notification): string
    {
        $vars = [
            '{{customer_name}}' => $user->name ?: 'Gardener',
            '{{order_number}}' => (string) ($data['order_number'] ?? ''),
            '{{order_total}}' => (string) ($data['order_total'] ?? ''),
            '{{tracking_number}}' => (string) ($data['tracking_number'] ?? ''),
            '{{title}}' => $notification->title,
            '{{body}}' => $notification->body,
        ];

        return strtr($text, $vars);
    }

    /**
     * @return array{label: ?string, url: ?string}
     */
    private function safeCta(array $data): array
    {
        $base = rtrim((string) env('CUSTOMER_WEB_URL', env('APP_URL', 'http://127.0.0.1:3000')), '/');
        if (! empty($data['order_id']) && is_numeric($data['order_id'])) {
            $basePath = (($data['audience'] ?? 'customer') === 'admin')
                ? '/orders/'
                : '/account/orders/';
            $webBase = (($data['audience'] ?? 'customer') === 'admin')
                ? rtrim((string) env('ADMIN_WEB_URL', env('APP_URL', 'http://127.0.0.1:3001')), '/')
                : $base;

            return [
                'label' => 'View order',
                'url' => ((($data['audience'] ?? 'customer') === 'admin') ? $webBase : $base).$basePath.$data['order_id'],
            ];
        }
        if (! empty($data['return_id']) && is_numeric($data['return_id'])) {
            return ['label' => 'View return', 'url' => $base.'/account/returns/'.$data['return_id']];
        }
        if (! empty($data['subscription_id']) && is_numeric($data['subscription_id'])) {
            return ['label' => 'View subscription', 'url' => $base.'/account/subscriptions/'.$data['subscription_id']];
        }
        if (! empty($data['product_slug']) && is_string($data['product_slug']) && preg_match('/^[a-z0-9\-]+$/i', $data['product_slug'])) {
            return ['label' => 'View product', 'url' => $base.'/product/'.$data['product_slug']];
        }
        if (! empty($data['campaign_slug']) && is_string($data['campaign_slug']) && preg_match('/^[a-z0-9\-]+$/i', $data['campaign_slug'])) {
            return ['label' => 'View offer', 'url' => $base.'/campaigns/'.$data['campaign_slug']];
        }

        return ['label' => null, 'url' => null];
    }
}
