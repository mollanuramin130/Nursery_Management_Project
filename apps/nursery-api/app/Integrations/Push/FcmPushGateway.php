<?php

namespace App\Integrations\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM sender — prefers Firebase HTTP v1 (service account), falls back to legacy server key.
 * Never expose FCM_SERVER_KEY / FIREBASE_CREDENTIALS to clients.
 */
class FcmPushGateway
{
    public function send(string $token, string $title, string $body, array $data = []): array
    {
        $credentialsPath = $this->resolveCredentialsPath();
        if ($credentialsPath !== null) {
            return $this->sendHttpV1($credentialsPath, $token, $title, $body, $data);
        }

        $key = $this->envString('FCM_SERVER_KEY');
        if ($key !== '') {
            return $this->sendLegacy($key, $token, $title, $body, $data);
        }

        if (app()->environment('production')) {
            Log::warning('fcm.unavailable', ['reason' => 'missing_credentials']);

            return ['ok' => false, 'mode' => 'unavailable', 'invalid_token' => false];
        }

        Log::info('fcm.local_stub', ['title' => $title]);

        return ['ok' => true, 'mode' => 'local_stub', 'invalid_token' => false];
    }

    public function mode(): string
    {
        if ($this->resolveCredentialsPath() !== null) {
            return 'http_v1';
        }
        if ($this->envString('FCM_SERVER_KEY') !== '') {
            return 'legacy_server_key';
        }

        return app()->environment('production') ? 'unavailable' : 'local_stub';
    }

    private function resolveCredentialsPath(): ?string
    {
        $path = $this->envString('FIREBASE_CREDENTIALS');
        if ($path === '') {
            $path = $this->envString('GOOGLE_APPLICATION_CREDENTIALS');
        }
        if ($path === '') {
            return null;
        }
        if (is_file($path)) {
            return $path;
        }
        $relative = base_path($path);
        if (is_file($relative)) {
            return $relative;
        }

        Log::warning('fcm.credentials_path_missing', ['configured' => true]);

        return null;
    }

    private function envString(string $key): string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $value) {
            if (is_string($value) && trim($value) !== '' && strtoupper(trim($value)) !== 'NULL') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @return array{ok:bool,mode:string,invalid_token:bool,raw?:mixed,error?:string}
     */
    private function sendHttpV1(string $credentialsPath, string $token, string $title, string $body, array $data): array
    {
        try {
            $json = json_decode((string) file_get_contents($credentialsPath), true);
            if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
                return ['ok' => false, 'mode' => 'http_v1', 'invalid_token' => false, 'error' => 'invalid_service_account_json'];
            }

            $accessToken = $this->accessToken($json);
            if ($accessToken === null) {
                return ['ok' => false, 'mode' => 'http_v1', 'invalid_token' => false, 'error' => 'oauth_token_failed'];
            }

            $stringData = collect($data)->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))->all();
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'HIGH',
                    ],
                ],
            ];

            $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
            $response = Http::withToken($accessToken)->timeout(15)->post($url, $message);
            $bodyJson = $response->json() ?? [];
            $ok = $response->successful();
            $invalid = false;
            $errorStatus = (string) data_get($bodyJson, 'error.status', '');
            $errorCode = (string) data_get($bodyJson, 'error.details.0.errorCode', '');
            if (in_array($errorStatus, ['NOT_FOUND', 'INVALID_ARGUMENT'], true)
                || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $invalid = true;
                $ok = false;
            }

            if (! $ok) {
                Log::warning('fcm.http_v1_send_failed', [
                    'status' => $response->status(),
                    'error_status' => $errorStatus,
                ]);
            }

            return ['ok' => $ok, 'mode' => 'http_v1', 'invalid_token' => $invalid, 'raw' => $bodyJson];
        } catch (\Throwable $e) {
            Log::error('fcm.http_v1_exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'mode' => 'http_v1', 'invalid_token' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $serviceAccount
     */
    private function accessToken(array $serviceAccount): ?string
    {
        $cacheKey = 'fcm_sa_token:'.sha1((string) $serviceAccount['client_email']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64url(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header.'.'.$claims;
        $key = openssl_pkey_get_private((string) $serviceAccount['private_key']);
        if ($key === false) {
            return null;
        }
        $signature = '';
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = $unsigned.'.'.$this->b64url($signature);

        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        $access = (string) ($response->json('access_token') ?? '');
        if ($access === '' || ! $response->successful()) {
            Log::warning('fcm.oauth_failed', ['status' => $response->status()]);

            return null;
        }

        Cache::put($cacheKey, $access, now()->addMinutes(50));

        return $access;
    }

    /**
     * @return array{ok:bool,mode:string,invalid_token:bool,raw?:mixed,error?:string}
     */
    private function sendLegacy(string $key, string $token, string $title, string $body, array $data): array
    {
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

            return ['ok' => $ok, 'mode' => 'legacy_server_key', 'invalid_token' => $invalid, 'raw' => $json];
        } catch (\Throwable $e) {
            Log::error('fcm.exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'mode' => 'legacy_server_key', 'invalid_token' => false, 'error' => $e->getMessage()];
        }
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
