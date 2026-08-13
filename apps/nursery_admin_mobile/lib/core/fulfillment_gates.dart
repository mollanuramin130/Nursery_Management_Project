/// Fulfillment UI helpers — order status is UPPER_SNAKE; shipment often lowercase.
/// Prefer server `actions` flags when present; these are fallback gates only.

bool canStartPicking(String orderStatus) => orderStatus == 'CONFIRMED';

bool canShip(String orderStatus) => orderStatus == 'PACKED';

bool canOutForDelivery(String orderStatus) => orderStatus == 'SHIPPED';

bool canFailDelivery(String orderStatus) => orderStatus == 'OUT_FOR_DELIVERY';

bool canRetryDelivery(String orderStatus) => orderStatus == 'DELIVERY_FAILED';

bool isShipmentShipped(String? shipmentStatus) =>
    (shipmentStatus ?? '').toLowerCase() == 'shipped';
