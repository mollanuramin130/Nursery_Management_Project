<?php

namespace App\Integrations\Push;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 legacy server-key sender.
 * Never expose FCM_SERVER_KEY to clients.
 */
class FcmPushGateway
{
    public function send(string $token, string $title, string $body, array $data = []): array
    {
        $key = (string) env('FCM_SERVER_KEY', '');
        if ($key === '') {
            if (app()->environment('production')) {
                Log::warning('fcm.unavailable', ['reason' => 'missing_server_key']);

                return ['ok' => false, 'mode' => 'unavailable', 'invalid_token' => false];
            }

            Log::info('fcm.local_stub', ['title' => $title]);

            return ['ok' => true, 'mode' => 'local_stub', 'invalid_token' => false];
        }

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => collect($data)->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))->all(),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$key,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);

            $json = $response->json() ?? [];
            $ok = $response->successful() && (int) ($json['success'] ?? 0) > 0;
            $invalid = false;
            $results = $json['results'][0] ?? [];
            if (isset($results['error']) && in_array($results['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                $invalid = true;
                $ok = false;
            }

            if (! $ok) {
                Log::warning('fcm.send_failed', [
                    'status' => $response->status(),
                    'error' => $results['error'] ?? null,
                ]);
            }

            return ['ok' => $ok, 'mode' => 'fcm', 'invalid_token' => $invalid, 'raw' => $json];
        } catch (\Throwable $e) {
            Log::error('fcm.exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'mode' => 'fcm', 'invalid_token' => false, 'error' => $e->getMessage()];
        }
    }
}
