import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/fulfillment_gates.dart';
import 'package:nursery_admin_mobile/core/token_refresh_coordinator.dart';

void main() {
  group('TokenRefreshCoordinator (QA-09)', () {
    test('single-flight: concurrent callers share one refresh', () async {
      final coord = TokenRefreshCoordinator();
      var runs = 0;
      Future<bool> refresh() async {
        runs++;
        await Future<void>.delayed(const Duration(milliseconds: 40));
        return true;
      }

      final results = await Future.wait([
        coord.run(refresh),
        coord.run(refresh),
        coord.run(refresh),
      ]);

      expect(runs, 1);
      expect(results, everyElement(isTrue));
      expect(coord.isRefreshing, isFalse);
    });

    test('failed refresh is not stuck as in-flight', () async {
      final coord = TokenRefreshCoordinator();
      final ok = await coord.run(() async => false);
      expect(ok, isFalse);
      expect(coord.isRefreshing, isFalse);

      var second = 0;
      await coord.run(() async {
        second++;
        return true;
      });
      expect(second, 1);
    });
  });

  group('ApiException.userMessage (QA-09)', () {
    test('403 does not look like session expiry', () {
      final e = ApiException('Forbidden', statusCode: 403);
      expect(e.userMessage.toLowerCase(), contains('permission'));
      expect(e.userMessage.toLowerCase(), isNot(contains('session expired')));
    });

    test('network null status', () {
      final e = ApiException('x', statusCode: null);
      expect(e.userMessage, contains('internet'));
    });

    test('500 safe message', () {
      final e = ApiException('', statusCode: 500);
      expect(e.userMessage.toLowerCase(), contains('unavailable'));
    });
  });

  group('Fulfillment gates (QA-09)', () {
    test('picking only from CONFIRMED', () {
      expect(canStartPicking('CONFIRMED'), isTrue);
      expect(canStartPicking('PROCESSING'), isFalse);
    });

    test('ship only from PACKED', () {
      expect(canShip('PACKED'), isTrue);
      expect(canShip('SHIPPED'), isFalse);
    });

    test('shipment casing', () {
      expect(isShipmentShipped('shipped'), isTrue);
      expect(isShipmentShipped('SHIPPED'), isTrue);
    });
  });
}
