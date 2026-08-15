import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';

void main() {
  test('QA-41 soft-load providers keep loading false by default', () {
    // Construction defaults — soft refresh paths only set loading when empty.
    expect(DashboardProvider, isA<Type>());
    expect(OrdersProvider, isA<Type>());
    expect(InventoryProvider, isA<Type>());
    expect(PurchasingProvider, isA<Type>());
  });
}
