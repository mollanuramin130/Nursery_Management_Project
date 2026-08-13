import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/token_refresh_coordinator.dart';

void main() {
  test('TokenRefreshCoordinator (QA-12) single-flight: concurrent callers share one refresh',
      () async {
    final coord = TokenRefreshCoordinator();
    var runs = 0;

    Future<bool> refresh() async {
      runs++;
      await Future<void>.delayed(const Duration(milliseconds: 30));
      return true;
    }

    final results = await Future.wait([
      coord.run(refresh),
      coord.run(refresh),
      coord.run(refresh),
    ]);

    expect(runs, 1);
    expect(results, everyElement(isTrue));
  });

  test('TokenRefreshCoordinator (QA-12) failed refresh is not stuck as in-flight', () async {
    final coord = TokenRefreshCoordinator();

    final first = await coord.run(() async {
      await Future<void>.delayed(const Duration(milliseconds: 10));
      return false;
    });
    expect(first, isFalse);
    expect(coord.isRefreshing, isFalse);

    final second = await coord.run(() async => true);
    expect(second, isTrue);
  });
}
