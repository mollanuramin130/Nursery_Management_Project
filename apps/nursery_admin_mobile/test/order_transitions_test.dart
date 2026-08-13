import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/order_transitions.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';

void main() {
  test('allowedOrderTransitions mirrors state machine UX gates', () {
    expect(allowedOrderTransitions('CONFIRMED'), ['PROCESSING', 'CANCELLED']);
    expect(allowedOrderTransitions('PACKED'), ['SHIPPED', 'CANCELLED']);
    expect(allowedOrderTransitions('SHIPPED'), ['OUT_FOR_DELIVERY', 'DELIVERED']);
    expect(allowedOrderTransitions('CANCELLED'), isEmpty);
    expect(allowedOrderTransitions('UNKNOWN'), isEmpty);
  });

  test('opsStatusLabel uses canonical QA-32 order labels', () {
    expect(opsStatusLabel('PENDING_PAYMENT'), 'Pending payment');
    expect(opsStatusLabel('OUT_FOR_DELIVERY'), 'Out for delivery');
    expect(opsStatusLabel('RETURN_REQUESTED'), 'Return requested');
    expect(opsStatusLabel('confirmed'), 'Confirmed');
  });

  test('opsPaymentStatusLabel uses canonical QA-35 payment labels', () {
    expect(opsPaymentStatusLabel('success'), 'Paid');
    expect(opsPaymentStatusLabel('pending'), 'Pending');
    expect(opsPaymentStatusLabel('refund_pending'), 'Refund pending');
  });
}
