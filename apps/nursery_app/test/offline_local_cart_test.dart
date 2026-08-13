import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('cart local add while mockOnly does not call success as server sync',
      () async {
    final offline = OfflineController(mode: MockDataMode.mockOnly);
    final api = ApiClient(SessionStorage());
    final catalog = CatalogRepository(api: api, offline: offline);
    final cart = CartProvider(api, offline: offline)..attachCatalog(catalog);

    await cart.fetch();
    expect(cart.localOnly, isTrue);
    expect(cart.cart.items, isNotEmpty);

    final before = cart.cart.itemCount;
    await cart.addItem(105, quantity: 1);
    expect(cart.localOnly, isTrue);
    expect(cart.cart.itemCount, greaterThan(before));
    expect(cart.message, contains('device'));
  });

  test('wishlist toggle persists locally in mockOnly', () async {
    final offline = OfflineController(mode: MockDataMode.mockOnly);
    final api = ApiClient(SessionStorage());
    final wish = WishlistProvider(api, offline: offline);
    await wish.bootstrap(signedIn: false);
    final added = await wish.toggle(203);
    expect(added, isTrue);
    expect(wish.contains(203), isTrue);
    expect(wish.localOnly, isTrue);
  });
}
