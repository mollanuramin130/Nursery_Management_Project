import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/models/models.dart';

void main() {
  group('FreeDelivery / Cart API mapping (QA-03)', () {
    test('maps free_delivery qualifies and remaining from API', () {
      final cart = Cart.fromJson({
        'items': [
          {
            'id': 1,
            'product_id': 10,
            'variant_id': null,
            'name': 'Plant',
            'unit_price': 549,
            'quantity': 2,
            'line_total': 1098,
          },
        ],
        'item_count': 2,
        'subtotal': 1098,
        'discount_total': 109.8,
        'tax_total': 0,
        'shipping_total': 0,
        'grand_total': 988.2,
        'coupon_code': 'WELCOME10',
        'currency': 'INR',
        'free_delivery': {
          'enabled': true,
          'threshold': 999,
          'remaining': 10.8,
          'qualifies': false,
        },
        'checkout_blocked': false,
        'warnings': [],
      });

      expect(cart.subtotal, 1098);
      expect(cart.discountTotal, 109.8);
      expect(cart.shippingTotal, 0);
      expect(cart.freeDelivery.qualifies, isFalse);
      expect(cart.freeDelivery.remaining, 10.8);
      expect(cart.freeDelivery.threshold, 999);
      expect(cart.items.single.variantId, isNull);
    });

    test('maps variant_id when present', () {
      final cart = Cart.fromJson({
        'items': [
          {
            'id': 2,
            'product_id': 11,
            'variant_id': 55,
            'name': 'Variant plant',
            'unit_price': 100,
            'quantity': 1,
            'line_total': 100,
          },
        ],
        'item_count': 1,
        'subtotal': 100,
        'discount_total': 0,
        'grand_total': 100,
        'free_delivery': {
          'enabled': true,
          'threshold': 999,
          'remaining': 899,
          'qualifies': false,
        },
      });
      expect(cart.items.single.variantId, 55);
    });

    test('null free_delivery falls back safely', () {
      final fd = FreeDelivery.fromJson(null);
      expect(fd.qualifies, isFalse);
      expect(fd.enabled, isTrue);
    });
  });
}
