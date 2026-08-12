<?php

namespace App\Integrations\Payment;

interface PaymentGatewayInterface
{
    public function provider(): string;

    /**
     * @return array{provider_order_id:string, client_payload:array, raw?:array}
     */
    public function createOrder(float $amount, string $currency, string $receipt, array $notes = []): array;

    public function verifySignature(string $providerOrderId, string $providerPaymentId, string $signature): bool;
}
