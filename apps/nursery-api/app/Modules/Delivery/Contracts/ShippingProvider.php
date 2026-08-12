<?php

namespace App\Modules\Delivery\Contracts;

interface ShippingProvider
{
    public function code(): string;

    /**
     * @param  array{order_id:int, order_number:string, warehouse_id?:int|null, carrier?:string|null, tracking_number?:string|null, tracking_url?:string|null, eta_date?:string|null, weight_grams?:int|null}  $payload
     * @return array{carrier:string, tracking_number:string, tracking_url:?string, eta_date:?string, meta:array}
     */
    public function createShipment(array $payload): array;

    public function cancelShipment(string $trackingNumber, array $context = []): bool;

    /**
     * @return list<array{status:string, description:?string, location:?string, event_at:string}>
     */
    public function getTracking(string $trackingNumber): array;
}
