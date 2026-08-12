<?php

namespace App\Integrations\Payment;

class CodGateway implements PaymentGatewayInterface
{
    public function provider(): string
    {
        return 'cod';
    }

    public function createOrder(float $amount, string $currency, string $receipt, array $notes = []): array
    {
        return [
            'provider_order_id' => 'cod_'.$receipt,
            'client_payload' => [
                'method' => 'cod',
                'amount' => $amount,
                'currency' => $currency,
            ],
            'raw' => ['cod' => true],
        ];
    }

    public function verifySignature(string $providerOrderId, string $providerPaymentId, string $signature): bool
    {
        return true;
    }
}
