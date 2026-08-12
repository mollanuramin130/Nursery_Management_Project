<?php

namespace App\Integrations\Payment;

use App\Shared\Exceptions\ApiException;

class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: env('PAYMENT_DRIVER', 'razorpay');

        return match ($name) {
            'razorpay' => new RazorpayGateway,
            'cod' => new CodGateway,
            default => throw new ApiException("Unsupported payment driver [{$name}]", 400, 'BAD_REQUEST'),
        };
    }
}
