import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';

class CartProvider extends ChangeNotifier {
  CartProvider(this._api);

  final ApiClient _api;

  Cart cart = Cart.empty();
  bool loading = false;
  bool mutating = false;
  String? message;
  String? error;
  bool get hasError => error != null;

  Future<void> fetch() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      cart = await _api.getData(
        '/cart',
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
    } catch (e) {
      // Keep last known cart — never pretend a network failure is an empty cart.
      error = e is ApiException
          ? e.message
          : 'Unable to load cart. Check your connection.';
    }
    loading = false;
    notifyListeners();
  }

  Future<void> addItem(int productId, {int quantity = 1}) async {
    message = null;
    try {
      cart = await _api.sendData(
        'POST',
        '/cart/items',
        body: {'product_id': productId, 'quantity': quantity},
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
      message = 'Added to cart';
      notifyListeners();
    } on ApiException catch (e) {
      message = e.message;
      notifyListeners();
      rethrow;
    }
  }

  Future<void> updateItem(int itemId, int quantity) async {
    mutating = true;
    notifyListeners();
    try {
      cart = await _api.sendData(
        'PUT',
        '/cart/items/$itemId',
        body: {'quantity': quantity},
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
    } on ApiException catch (e) {
      message = e.message;
      rethrow;
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> removeItem(int itemId) async {
    mutating = true;
    notifyListeners();
    try {
      cart = await _api.sendData(
        'DELETE',
        '/cart/items/$itemId',
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
    } on ApiException catch (e) {
      message = e.message;
      rethrow;
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> applyCoupon(String code) async {
    cart = await _api.sendData(
      'POST',
      '/cart/apply-coupon',
      body: {'code': code},
      map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
    );
    message = 'Coupon applied';
    notifyListeners();
  }

  Future<void> removeCoupon() async {
    cart = await _api.sendData(
      'DELETE',
      '/cart/coupon',
      map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
    );
    notifyListeners();
  }

  Future<void> clearCart() async {
    mutating = true;
    notifyListeners();
    try {
      cart = await _api.sendData(
        'DELETE',
        '/cart',
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
      message = 'Cart cleared';
    } on ApiException catch (e) {
      message = e.message;
      rethrow;
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> moveToWishlist(int itemId) async {
    mutating = true;
    notifyListeners();
    try {
      cart = await _api.sendData(
        'POST',
        '/cart/items/$itemId/move-to-wishlist',
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
      message = 'Moved to wishlist';
    } on ApiException catch (e) {
      message = e.message;
      rethrow;
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  void replaceCart(Cart next) {
    cart = next;
    error = null;
    notifyListeners();
  }
}
