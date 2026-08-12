import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/permissions.dart';
import 'package:nursery_admin_mobile/models/models.dart';

void main() {
  group('RBAC helpers', () {
    test('staff detection rejects customer-only', () {
      expect(
        isStaffUser(roles: ['customer'], permissions: []),
        isFalse,
      );
      expect(
        isStaffUser(roles: ['inventory_manager'], permissions: ['inventory.view']),
        isTrue,
      );
    });

    test('super_admin bypasses permission checks', () {
      expect(
        hasPermission([], ['super_admin'], 'inventory.adjust'),
        isTrue,
      );
    });

    test('hasAnyPermission matches owned slugs', () {
      expect(
        hasAnyPermission(
          ['orders.view', 'inventory.view'],
          ['order_manager'],
          ['inventory.adjust', 'orders.view'],
        ),
        isTrue,
      );
    });
  });

  group('models', () {
    test('AdminUser.fromJson', () {
      final u = AdminUser.fromJson({
        'id': 1,
        'name': 'Ops',
        'email': 'ops@example.com',
        'roles': ['admin'],
        'permissions': ['orders.view', 'inventory.view'],
      });
      expect(u.can('orders.view'), isTrue);
      expect(u.can('inventory.adjust'), isFalse);
    });

    test('InventoryRow sellable fallback', () {
      final r = InventoryRow.fromJson({
        'id': 9,
        'product_id': 3,
        'product_name': 'Snake Plant',
        'sku': 'SP-1',
        'qty_on_hand': 10,
        'qty_reserved': 2,
        'quantity_available': 8,
        'stock_status': 'IN_STOCK',
      });
      expect(r.sellable, 8);
    });
  });
}
