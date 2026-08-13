import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_asset_store.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('CatalogRepository mock-only', () {
    late CatalogRepository repo;

    setUp(() {
      final offline = OfflineController(mode: MockDataMode.mockOnly);
      final api = ApiClient(SessionStorage());
      repo = CatalogRepository(
        api: api,
        offline: offline,
        assets: MockAssetStore(),
        local: OfflineLocalStore(),
      );
    });

    test('home loads realistic products from assets', () async {
      final home = await repo.getHome();
      expect(home.source, DataSourceKind.mock);
      expect(home.data.featuredProducts, isNotEmpty);
      expect(
        home.data.featuredProducts.any((p) => p.slug == 'money-plant'),
        isTrue,
      );
    });

    test('products search money locally', () async {
      final list = await repo.listProducts(
        filters: const CatalogFilters(q: 'money'),
      );
      expect(list.source, DataSourceKind.mock);
      expect(list.data.any((p) => p.slug.contains('money')), isTrue);
    });

    test('product detail by slug', () async {
      final detail = await repo.getProduct('snake-plant');
      expect(detail.data.summary.name, 'Snake Plant');
      expect(detail.data.description, isNotEmpty);
    });

    test('orders mock seed', () async {
      final orders = await repo.getOrders();
      expect(orders.data.length, greaterThanOrEqualTo(2));
      expect(orders.data.first.orderNumber, contains('ORD-'));
    });

    test('search suggestions offline', () async {
      final s = await repo.searchSuggestions('tulsi');
      expect(s.data, isNotEmpty);
    });
  });
}
