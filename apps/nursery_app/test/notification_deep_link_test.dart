import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/services/notification_deep_link.dart';
import 'package:nursery_app/services/push_registration.dart';

void main() {
  test('notificationDeepLink prefers safe route', () {
    expect(
      notificationDeepLink({
        'route': '/account/orders/5',
        'order_id': 5,
      }),
      '/orders/5',
    );
  });

  test('notificationDeepLink order and return', () {
    expect(notificationDeepLink({'order_id': 8}), '/orders/8');
    expect(notificationDeepLink({'return_id': 2}), '/account/returns/2');
  });

  test('QA-36-005: bare /returns/* remaps to customer returns', () {
    expect(
      notificationDeepLink({'route': '/returns/88', 'return_id': 88}),
      '/account/returns/88',
    );
  });

  test('deviceRegisterBody includes push token when present', () {
    final body = deviceRegisterBody(
      platform: 'android',
      deviceId: 'd1',
      appVersion: '1.0.0',
      pushToken: 'tok',
    );
    expect(body['push_token'], 'tok');
    final without = deviceRegisterBody(
      platform: 'android',
      deviceId: 'd1',
      appVersion: '1.0.0',
    );
    expect(without.containsKey('push_token'), isFalse);
  });
}
