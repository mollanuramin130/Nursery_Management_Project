import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/data/cache_envelope.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/providers/offline_controller.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  test('mockOnly home does not block later API cache write', () async {
    final offline = OfflineController(mode: MockDataMode.mockOnly);
    final local = OfflineLocalStore(
      storage: const FlutterSecureStorage(),
    );
    final api = ApiClient(
      SessionStorage(storage: const FlutterSecureStorage()),
    );
    final repo = CatalogRepository(
      api: api,
      offline: offline,
      local: local,
    );

    final mockHome = await repo.getHome();
    expect(mockHome.source, DataSourceKind.mock);

    // Simulate API envelope write (server price wins path).
    final wrote = await local.writeHomeEnvelope(
      CacheEnvelope(
        data: {
          'banners': [],
          'featured_products': [
            {
              'id': 107,
              'name': 'Tulsi (Holy Basil)',
              'slug': 'tulsi-holy-basil',
              'price': 219.0,
              'currency': 'INR',
              'stock_status': 'in_stock',
            },
          ],
          'campaigns': [],
        },
        source: DataSourceKind.remote,
        cachedAt: DateTime.now(),
      ),
    );
    expect(wrote, isTrue);

    // Mock write must not replace API envelope.
    final blocked = await local.writeHomeEnvelope(
      CacheEnvelope(
        data: {
          'banners': [],
          'featured_products': [
            {
              'id': 107,
              'name': 'Tulsi (Holy Basil)',
              'slug': 'tulsi-holy-basil',
              'price': 199.0,
              'currency': 'INR',
              'stock_status': 'in_stock',
            },
          ],
          'campaigns': [],
        },
        source: DataSourceKind.mock,
        cachedAt: DateTime.now(),
      ),
    );
    expect(blocked, isFalse);

    final peeked = await repo.peekHome();
    expect(peeked, isNotNull);
    expect(peeked!.source, isNot(DataSourceKind.mock));
    final tulsi = peeked.data.featuredProducts
        .where((p) => p.slug == 'tulsi-holy-basil')
        .first;
    expect(tulsi.price, 219.0);
  });

  test('OfflineController reconnect bumps syncGeneration', () {
    final offline = OfflineController();
    final before = offline.syncGeneration;
    offline.notifyReconnected();
    expect(offline.syncGeneration, before + 1);
    expect(offline.syncing, isTrue);
  });
}
