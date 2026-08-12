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

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $key.':'.$secret,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            \Illuminate\Support\Facades\Log::error('razorpay.order_create_failed', [
                'http_status' => $status,
                'curl_error' => $error ?: null,
            ]);

            throw new ApiException(
                message: 'Payment provider temporarily unavailable',
                statusCode: 502,
                errorCode: 'EXTERNAL_API_ERROR',
            );
        }

        $json = json_decode($body, true) ?: [];
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
}
