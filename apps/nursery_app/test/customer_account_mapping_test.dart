import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/models/customer_account_models.dart';

void main() {
  group('CustomerReturnSummary mapping (QA-PAR-001)', () {
    test('maps API return list row', () {
      final r = CustomerReturnSummary.fromJson({
        'id': 12,
        'order_id': 90,
        'order_number': 'ORD-1',
        'status': 'RETURN_REQUESTED',
        'notes': 'Leaf damage',
        'created_at': '2026-08-12T10:00:00+05:30',
        'items': [
          {
            'id': 1,
            'quantity': 1,
            'name': 'Snake Plant',
            'sku': 'SP-1',
            'reason': 'damaged',
          },
        ],
      });
      expect(r.id, 12);
      expect(r.orderId, 90);
      expect(r.orderNumber, 'ORD-1');
      expect(r.statusLabel, 'RETURN REQUESTED');
      expect(r.items, hasLength(1));
      expect(r.items.first.name, 'Snake Plant');
    });
  });

  group('CustomerMyReview mapping (QA-PAR-002)', () {
    test('maps API my-review row', () {
      final r = CustomerMyReview.fromJson({
        'id': 5,
        'product_id': 101,
        'product_name': 'Money Plant',
        'product_slug': 'money-plant',
        'rating': 5,
        'title': 'Great',
        'body': 'Healthy plant',
        'status': 'approved',
        'created_at': '2026-08-01T00:00:00Z',
      });
      expect(r.productSlug, 'money-plant');
      expect(r.rating, 5);
      expect(r.status, 'approved');
    });
  });

  group('parseAccountListPayload', () {
    test('accepts bare list', () {
      final rows = parseAccountListPayload([
        {'id': 1},
        {'id': 2},
      ]);
      expect(rows.length, 2);
    });

    test('accepts nested data list', () {
      final rows = parseAccountListPayload({
        'data': [
          {'id': 3},
        ],
      });
      expect(rows.single['id'], 3);
    });

    test('empty on garbage', () {
      expect(parseAccountListPayload(null), isEmpty);
      expect(parseAccountListPayload('x'), isEmpty);
    });
  });
}
