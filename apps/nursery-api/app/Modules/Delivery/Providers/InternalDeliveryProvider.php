<?php

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Contracts\ShippingProvider;
use App\Shared\Exceptions\ApiException;

/**
 * Default nursery delivery partner — generates internal tracking numbers.
 * Future carriers implement ShippingProvider without changing order lifecycle.
 */
class InternalDeliveryProvider implements ShippingProvider
{
    public function code(): string
    {
        return 'internal';
    }

    public function createShipment(array $payload): array
    {
        $tracking = $payload['tracking_number'] ?? null;
        if (! $tracking) {
            $tracking = 'GL-'.strtoupper($this->code()).'-'.$payload['order_number'].'-'.now()->format('YmdHis');
        }

        $carrier = $payload['carrier'] ?? 'GreenLeaf Delivery';

        return [
            'carrier' => $carrier,
            'tracking_number' => $tracking,
            'tracking_url' => $payload['tracking_url'] ?? null,
            'eta_date' => $payload['eta_date'] ?? null,
            'meta' => [
                'provider' => $this->code(),
            ],
        ];
    }

    public function cancelShipment(string $trackingNumber, array $context = []): bool
    {
        // Internal shipments are cancelled by order/shipment status — no external API.
        return true;
    }

    public function getTracking(string $trackingNumber): array
    {
        if ($trackingNumber === '') {
            throw new ApiException('Tracking number required', 422, 'VALIDATION_ERROR');
        }

        // External pull not available; Admin/customer use shipment_events stored locally.
        return [];
    }
}
