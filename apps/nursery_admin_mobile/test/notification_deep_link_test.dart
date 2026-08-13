import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/services/notification_deep_link.dart';

void main() {
  test('adminNotificationDeepLink uses route and order', () {
    expect(
      adminNotificationDeepLink({'route': '/orders/3', 'type': 'new_order'}),
      '/orders/3',
    );
    expect(adminNotificationDeepLink({'order_id': 9}), '/orders/9');
  });

  test('return deep link avoids missing /returns route', () {
    expect(
      adminNotificationDeepLink({'return_id': 4, 'order_id': 12}),
      '/orders/12',
    );
    expect(adminNotificationDeepLink({'return_id': 4}), '/orders');
  });

  test('QA-35: API route=/returns/* remaps to order (ops has no returns UI)', () {
    expect(
      adminNotificationDeepLink({
        'route': '/returns/88',
        'return_id': 88,
        'order_id': 9069,
      }),
      '/orders/9069',
    );
    expect(
      adminNotificationDeepLink({'route': '/returns/88', 'return_id': 88}),
      '/orders',
    );
  });
}
