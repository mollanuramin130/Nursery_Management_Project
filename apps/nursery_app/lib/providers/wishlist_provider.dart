import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';

/// Tracks wishlist product IDs for heart sync across catalog/PDP.
class WishlistProvider extends ChangeNotifier {
  WishlistProvider(this._api);

  final ApiClient _api;

  final Set<int> _ids = {};
  bool loading = false;
  bool _bootstrapped = false;

  bool get bootstrapped => _bootstrapped;
  Set<int> get ids => _ids;
  bool contains(int productId) => _ids.contains(productId);

  Future<void> bootstrap({required bool signedIn}) async {
    if (!signedIn) {
      _ids.clear();
      _bootstrapped = true;
      notifyListeners();
      return;
    }
    loading = true;
    notifyListeners();
    try {
      final rows = await _api.getData(
        '/wishlist',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) {
              final product = e['product'];
              if (product is Map && product['id'] != null) {
                return product['id'] as int;
              }
              return e['product_id'] as int?;
            })
            .whereType<int>()
            .toSet(),
      );
      _ids
        ..clear()
        ..addAll(rows);
    } catch (_) {
      // Keep previous ids on transient failure.
    }
    loading = false;
    _bootstrapped = true;
    notifyListeners();
  }

  Future<void> add(int productId) async {
    await _api.sendData(
      'POST',
      '/wishlist',
      body: {'product_id': productId},
      map: (_) => null,
    );
    _ids.add(productId);
    notifyListeners();
  }

  Future<void> remove(int productId) async {
    await _api.sendData('DELETE', '/wishlist/$productId', map: (_) => null);
    _ids.remove(productId);
    notifyListeners();
  }

  Future<bool> toggle(int productId) async {
    final wasSaved = _ids.contains(productId);
    if (wasSaved) {
      _ids.remove(productId);
      notifyListeners();
      try {
        await _api.sendData('DELETE', '/wishlist/$productId', map: (_) => null);
        return false;
      } catch (_) {
        _ids.add(productId);
        notifyListeners();
        rethrow;
      }
    }
    _ids.add(productId);
    notifyListeners();
    try {
      await _api.sendData(
        'POST',
        '/wishlist',
        body: {'product_id': productId},
        map: (_) => null,
      );
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 409) {
        return true;
      }
      _ids.remove(productId);
      notifyListeners();
      rethrow;
    } catch (_) {
      _ids.remove(productId);
      notifyListeners();
      rethrow;
    }
  }

  /// Atomically moves wishlist product into cart. Returns updated cart payload.
  Future<Cart> moveToCart(int productId, {int quantity = 1}) async {
    final data = await _api.sendData(
      'POST',
      '/wishlist/$productId/move-to-cart',
      body: {'quantity': quantity},
      map: (raw) => Map<String, dynamic>.from(raw as Map),
    );
    _ids.remove(productId);
    notifyListeners();
    final cartJson = data['cart'];
    if (cartJson is Map) {
      return Cart.fromJson(Map<String, dynamic>.from(cartJson));
    }
    throw ApiException('Invalid move-to-cart response', statusCode: 500);
  }

  void clearLocal() {
    _ids.clear();
    notifyListeners();
  }
}
