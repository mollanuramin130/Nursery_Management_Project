import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/fulfillment_gates.dart';

void main() {
  group('QA-05/QA-09 fulfillment action gates', () {
    test('picking only from CONFIRMED', () {
      expect(canStartPicking('CONFIRMED'), isTrue);
      expect(canStartPicking('PROCESSING'), isFalse);
      expect(canStartPicking('PACKED'), isFalse);
    });

    test('ship only from PACKED', () {
      expect(canShip('PACKED'), isTrue);
      expect(canShip('SHIPPED'), isFalse);
    });

    test('delivery fail/retry gates', () {
      expect(canFailDelivery('OUT_FOR_DELIVERY'), isTrue);
      expect(canRetryDelivery('DELIVERY_FAILED'), isTrue);
      expect(canRetryDelivery('OUT_FOR_DELIVERY'), isFalse);
    });

    test('shipment vs order status casing', () {
      expect(isShipmentShipped('shipped'), isTrue);
      expect(isShipmentShipped('SHIPPED'), isTrue);
      expect(isShipmentShipped('pending'), isFalse);
    });
  });
}
