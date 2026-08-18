import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/providers/offline_controller.dart';

void main() {
  test('QA-42A cache peek does not show saved-data banner', () {
    final offline = OfflineController();
    expect(offline.servingLocal, isFalse);
    // Stale-while-revalidate peek during normal refresh.
    offline.markSource(DataSourceKind.cache);
    expect(offline.servingLocal, isFalse);
    expect(offline.localBannerSuffix(NetworkKind.online), isNull);
  });

  test('QA-42A genuine degraded fallback shows offline banner copy', () {
    final offline = OfflineController();
    offline.markDegraded(DataSourceKind.cache);
    expect(offline.servingLocal, isTrue);
    expect(
      offline.localBannerSuffix(NetworkKind.offline),
      contains('offline'),
    );
    expect(
      offline.localBannerSuffix(NetworkKind.apiUnavailable),
      contains('saved data'),
    );
    expect(
      offline.localBannerSuffix(NetworkKind.apiUnavailable)!.toLowerCase(),
      isNot(contains("you're offline")),
    );
  });

  test('QA-42A quiet syncing alone does not imply offline banner text', () {
    final offline = OfflineController();
    offline.beginSync();
    expect(offline.syncing, isTrue);
    expect(offline.servingLocal, isFalse);
    expect(offline.localBannerSuffix(NetworkKind.online), isNull);
  });

  test('QA-42A remote success clears degraded without a back-online flash', () {
    final offline = OfflineController();
    offline.markDegraded(DataSourceKind.cache);
    offline.markRemoteOk();
    expect(offline.servingLocal, isFalse);
    expect(offline.showBackOnline, isFalse);
    expect(offline.localBannerSuffix(NetworkKind.online), isNull);
  });

  test('QA-42A mock-only marks degraded', () {
    final offline = OfflineController(mode: MockDataMode.mockOnly);
    offline.markSource(DataSourceKind.mock, asDegraded: true);
    expect(offline.servingLocal, isTrue);
  });
}
