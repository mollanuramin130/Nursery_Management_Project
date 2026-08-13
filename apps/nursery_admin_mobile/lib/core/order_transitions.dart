// Mirrors Admin Web `order-transitions.ts` / backend OrderStateMachine for UX.
// Backend remains authoritative.

const Map<String, List<String>> kOrderTransitions = {
  'PENDING_PAYMENT': ['CONFIRMED', 'PAYMENT_FAILED', 'CANCELLED'],
  'PAYMENT_FAILED': ['PENDING_PAYMENT', 'CANCELLED'],
  'CONFIRMED': ['PROCESSING', 'CANCELLED'],
  'PROCESSING': ['PACKED', 'CANCELLED'],
  'PACKED': ['SHIPPED', 'CANCELLED'],
  'SHIPPED': ['OUT_FOR_DELIVERY', 'DELIVERED'],
  'OUT_FOR_DELIVERY': ['DELIVERED', 'DELIVERY_FAILED'],
  'DELIVERY_FAILED': ['OUT_FOR_DELIVERY', 'DELIVERED'],
  'DELIVERED': ['RETURN_REQUESTED'],
  'RETURN_REQUESTED': ['RETURNED', 'REFUNDED', 'DELIVERED'],
  'RETURNED': ['REFUNDED'],
  'CANCELLED': <String>[],
  'REFUNDED': <String>[],
};

List<String> allowedOrderTransitions(String status) =>
    List<String>.from(kOrderTransitions[status] ?? const <String>[]);
