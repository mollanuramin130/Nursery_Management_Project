<?php

namespace App\Integrations\Payment;

use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Str;

class RazorpayGateway implements PaymentGatewayInterface
{
    public function provider(): string
    {
        return 'razorpay';
    }

    public function createOrder(float $amount, string $currency, string $receipt, array $notes = []): array
    {
        $amountPaise = (int) round($amount * 100);
        $key = (string) env('RAZORPAY_KEY', '');
        $secret = (string) env('RAZORPAY_SECRET', '');

        // Local stub when keys are not configured — never in production.
        if ($key === '' || $secret === '') {
            if (app()->environment('production')) {
                throw new ApiException(
                    'Payment gateway is not configured',
                    503,
                    'PAYMENT_GATEWAY_UNAVAILABLE',
                );
            }

            $providerOrderId = 'order_local_'.Str::lower(Str::random(14));

            return [
                'provider_order_id' => $providerOrderId,
                'client_payload' => [
                    'key' => $key !== '' ? $key : 'rzp_test_local',
                    'order_id' => $providerOrderId,
                    'amount' => $amountPaise,
                    'currency' => strtoupper($currency),
                    'name' => env('APP_NAME', 'GreenLeaf Nursery'),
                    'notes' => $notes,
                    'mode' => 'local_stub',
                ],
                'raw' => ['stub' => true],
            ];
        }

        $payload = [
            'amount' => $amountPaise,
            'currency' => strtoupper($currency),
            'receipt' => $receipt,
            'notes' => $notes,
            'payment_capture' => 1,
        ];

        $json = $this->razorpayRequest('POST', 'https://api.razorpay.com/v1/orders', $payload, $key, $secret);
        $providerOrderId = $json['id'] ?? null;
        if (! $providerOrderId) {
            throw new ApiException('Invalid payment provider response', 502, 'EXTERNAL_API_ERROR');
        }

        return [
            'provider_order_id' => $providerOrderId,
            'client_payload' => [
                'key' => $key,
                'order_id' => $providerOrderId,
                'amount' => $amountPaise,
                'currency' => strtoupper($currency),
                'name' => env('APP_NAME', 'GreenLeaf Nursery'),
            ],
            'raw' => $json,
        ];
    }

    /**
     * Enrich an existing createOrder result for UPI Dynamic QR / Intent / Checkout.
     * Amount authority remains the server order total already used in createOrder.
     *
     * @param  array{provider_order_id:string, client_payload:array, raw?:array}  $created
     * @return array{provider_order_id:string, client_payload:array, raw?:array, meta:array}
     */
    public function withUpiChannel(array $created, float $amount, string $currency, string $upiMode, string $receipt, array $notes = []): array
    {
        $upiMode = in_array($upiMode, ['dynamic_qr', 'upi_intent', 'checkout'], true) ? $upiMode : 'dynamic_qr';
        $amountPaise = (int) round($amount * 100);
        $expiresAt = now()->addMinutes(15)->toIso8601String();
        $payload = $created['client_payload'];
        $isStub = ($payload['mode'] ?? null) === 'local_stub';
        $meta = [
            'upi_mode' => $upiMode,
            'channel' => 'upi',
            'expires_at' => $expiresAt,
        ];

        $upiLink = $this->buildUpiDeepLink($amount, $currency, $receipt);

        if ($upiMode === 'dynamic_qr' || $upiMode === 'upi_intent') {
            $payload['channel'] = 'upi';
            $payload['upi_mode'] = $upiMode;
            $payload['expires_at'] = $expiresAt;
            $payload['qr_data'] = $upiLink;
            $payload['upi_intent_url'] = $upiLink;
            // Prefer UPI inside Checkout when opening the hosted sheet as fallback.
            $payload['method'] = [
                'upi' => true,
                'card' => false,
                'netbanking' => false,
                'wallet' => false,
            ];

            if (! $isStub) {
                $qr = $this->tryCreateRazorpayQr($amountPaise, $receipt, $notes);
                if ($qr !== null) {
                    $payload['qr_image_url'] = $qr['image_url'] ?? null;
                    $payload['provider_qr_id'] = $qr['id'] ?? null;
                    if (! empty($qr['image_url'])) {
                        // Prefer provider image when available; keep qr_data for local encode fallback.
                        $meta['provider_qr_id'] = $qr['id'] ?? null;
                        $meta['qr_image_url'] = $qr['image_url'] ?? null;
                    }
                    $created['raw'] = array_merge($created['raw'] ?? [], ['qr' => $qr]);
                }
            } else {
                $payload['stub_confirm_allowed'] = true;
            }
        } else {
            // checkout mode: full Checkout with UPI preferred.
            $payload['channel'] = 'upi';
            $payload['upi_mode'] = 'checkout';
            $payload['expires_at'] = $expiresAt;
            $payload['method'] = [
                'upi' => true,
                'card' => true,
                'netbanking' => true,
                'wallet' => true,
            ];
        }

        $meta['qr_data'] = $payload['qr_data'] ?? null;
        $meta['upi_intent_url'] = $payload['upi_intent_url'] ?? null;

        $created['client_payload'] = $payload;
        $created['meta'] = $meta;

        return $created;
    }

    /**
     * Rebuild UPI client payload fields from a pending payment row (idempotent re-initiate).
     */
    public function presentPendingUpiPayload(array $basePayload, ?array $meta): array
    {
        if (! is_array($meta) || ($meta['channel'] ?? null) !== 'upi') {
            return $basePayload;
        }

        $basePayload['channel'] = 'upi';
        $basePayload['upi_mode'] = $meta['upi_mode'] ?? 'dynamic_qr';
        $basePayload['expires_at'] = $meta['expires_at'] ?? null;
        if (! empty($meta['qr_data'])) {
            $basePayload['qr_data'] = $meta['qr_data'];
            $basePayload['upi_intent_url'] = $meta['upi_intent_url'] ?? $meta['qr_data'];
        }
        if (! empty($meta['qr_image_url'])) {
            $basePayload['qr_image_url'] = $meta['qr_image_url'];
        }
        if (! empty($meta['provider_qr_id'])) {
            $basePayload['provider_qr_id'] = $meta['provider_qr_id'];
        }
        if (($basePayload['mode'] ?? null) === 'local_stub') {
            $basePayload['stub_confirm_allowed'] = true;
        }
        $basePayload['method'] = [
            'upi' => true,
            'card' => ($meta['upi_mode'] ?? '') === 'checkout',
            'netbanking' => ($meta['upi_mode'] ?? '') === 'checkout',
            'wallet' => ($meta['upi_mode'] ?? '') === 'checkout',
        ];

        return $basePayload;
    }

    public function verifySignature(string $providerOrderId, string $providerPaymentId, string $signature): bool
    {
        $secret = (string) env('RAZORPAY_SECRET', '');

        // Local stub verify — never in production.
        if ($secret === '') {
            if (app()->environment('production')) {
                return false;
            }

            return str_starts_with($signature, 'local_')
                || $signature === hash('sha256', $providerOrderId.'|'.$providerPaymentId);
        }

        $expected = hash_hmac('sha256', $providerOrderId.'|'.$providerPaymentId, $secret);

        return hash_equals($expected, $signature);
    }

    private function buildUpiDeepLink(float $amount, string $currency, string $receipt): string
    {
        $pa = (string) env('UPI_PAYEE_VPA', 'greenleaf@razorpay');
        $pn = rawurlencode((string) env('APP_NAME', 'GreenLeaf Nursery'));
        $am = number_format($amount, 2, '.', '');
        $cu = strtoupper($currency ?: 'INR');
        $tn = rawurlencode($receipt);

        return "upi://pay?pa={$pa}&pn={$pn}&am={$am}&cu={$cu}&tn={$tn}";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryCreateRazorpayQr(int $amountPaise, string $receipt, array $notes): ?array
    {
        $key = (string) env('RAZORPAY_KEY', '');
        $secret = (string) env('RAZORPAY_SECRET', '');
        if ($key === '' || $secret === '') {
            return null;
        }

        try {
            $payload = [
                'type' => 'upi_qr',
                'name' => env('APP_NAME', 'GreenLeaf Nursery'),
                'usage' => 'single_use',
                'fixed_amount' => true,
                'payment_amount' => $amountPaise,
                'description' => $receipt,
                'close_by' => now()->addMinutes(15)->timestamp,
                'notes' => $notes,
            ];

            return $this->razorpayRequest('POST', 'https://api.razorpay.com/v1/payments/qr_codes', $payload, $key, $secret);
        } catch (\Throwable $e) {
            // Fallback: Checkout / deep-link still available; do not fail initiate.
            \Illuminate\Support\Facades\Log::warning('razorpay.qr_create_failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function razorpayRequest(string $method, string $url, array $payload, string $key, string $secret): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $key.':'.$secret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            \Illuminate\Support\Facades\Log::error('razorpay.request_failed', [
                'url' => $url,
                'http_status' => $status,
                'curl_error' => $error ?: null,
            ]);

            throw new ApiException(
                message: 'Payment provider temporarily unavailable',
                statusCode: 502,
                errorCode: 'EXTERNAL_API_ERROR',
            );
        }

        return json_decode($body, true) ?: [];
    }
}
