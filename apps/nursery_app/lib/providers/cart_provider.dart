import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/offline_controller.dart';

class CartProvider extends ChangeNotifier {
  CartProvider(
    this._api, {
    OfflineController? offline,
    OfflineLocalStore? local,
    CatalogRepository? catalog,
  }) : _offline = offline,
       _local = local ?? OfflineLocalStore(),
       _catalog = catalog;

  final ApiClient _api;
  final OfflineController? _offline;
  final OfflineLocalStore _local;
  CatalogRepository? _catalog;

  Cart cart = Cart.empty();
  bool loading = false;
  bool mutating = false;
  String? message;
  String? error;
  bool localOnly = false;

  bool get hasError => error != null;

  void attachCatalog(CatalogRepository catalog) {
    _catalog = catalog;
  }

  Future<void> fetch() async {
    loading = true;
    error = null;
    notifyListeners();

    final forceLocal = _offline?.mode == MockDataMode.mockOnly ||
        _offline?.mode == MockDataMode.offlineSimulation ||
        _offline?.simulation == NetworkSimulation.offline ||
        _offline?.simulation == NetworkSimulation.apiError ||
        _offline?.simulation == NetworkSimulation.timeout;

    if (!forceLocal) {
      try {
        cart = await _api.getData(
          '/cart',
          map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
        );
        error = null;
        localOnly = false;
        await _persistCart();
        loading = false;
        notifyListeners();
        return;
      } catch (e) {
        if (!_isTransportOrServer(e)) {
          error = e is ApiException
              ? e.message
              : 'Unable to load cart. Check your connection.';
          loading = false;
          notifyListeners();
          return;
        }
      }
    }

    // Offline / API down: local cache → mock seed.
    final cached = await _local.readCartJson();
    if (cached != null) {
      cart = Cart.fromJson(cached);
      localOnly = true;
      error = null;
      _offline?.markSource(DataSourceKind.cache);
      loading = false;
      notifyListeners();
      return;
    }

    if (_catalog != null) {
      try {
        final mock = await _catalog!.getMockCart();
        cart = mock.data;
        localOnly = true;
        error = null;
        await _persistCart();
        loading = false;
        notifyListeners();
        return;
      } catch (_) {}
    }

    // Keep last known cart — never pretend failure is empty if we had items.
    error = "You're offline. Showing saved cart when available.";
    localOnly = true;
    loading = false;
    notifyListeners();
  }

  Future<void> addItem(int productId, {int quantity = 1}) async {
    message = null;
    if (_useLocalMutations) {
      await _localAdd(productId, quantity: quantity);
      return;
    }
    try {
      cart = await _api.sendData(
        'POST',
        '/cart/items',
        body: {'product_id': productId, 'quantity': quantity},
        map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      error = null;
      localOnly = false;
      message = 'Added to cart';
      await _persistCart();
      notifyListeners();
    } on ApiException catch (e) {
      if (_isTransportOrServer(e)) {
        await _localAdd(productId, quantity: quantity);
        return;
      }
      message = e.message;
      notifyListeners();
      rethrow;
    } catch (e) {
      if (_isTransportOrServer(e)) {
        await _localAdd(productId, quantity: quantity);
        return;
      }
      message = 'Unable to add item. Please try again.';
      notifyListeners();
      rethrow;
    }
  }

  Future<void> updateItem(int itemId, int quantity) async {
    if (mutating) return;
    mutating = true;
    notifyListeners();
    try {
      if (_useLocalMutations) {
        await _localUpdate(itemId, quantity);
        return;
      }
      try {
        cart = await _api.sendData(
          'PUT',
          '/cart/items/$itemId',
          body: {'quantity': quantity},
          map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
        );
        error = null;
        localOnly = false;
        await _persistCart();
      } catch (e) {
        if (_isTransportOrServer(e)) {
          await _localUpdate(itemId, quantity);
          return;
        }
        if (e is ApiException) message = e.message;
        rethrow;
      }
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> removeItem(int itemId) async {
    if (mutating) return;
    mutating = true;
    notifyListeners();
    try {
      if (_useLocalMutations) {
        await _localRemove(itemId);
        return;
      }
      try {
        cart = await _api.sendData(
          'DELETE',
          '/cart/items/$itemId',
          map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
        );
        error = null;
        localOnly = false;
        await _persistCart();
      } catch (e) {
        if (_isTransportOrServer(e)) {
          await _localRemove(itemId);
          return;
        }
        if (e is ApiException) message = e.message;
        rethrow;
      }
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> applyCoupon(String code) async {
    if (_useLocalMutations || localOnly) {
      message =
          'Coupons need an internet connection. Your cart is saved on this device.';
      notifyListeners();
      throw ApiException(message!, statusCode: null);
    }
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
    if (_useLocalMutations || localOnly) {
      message = 'Reconnect to manage coupons.';
      notifyListeners();
      throw ApiException(message!, statusCode: null);
    }
    cart = await _api.sendData(
      'DELETE',
      '/cart/coupon',
      map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
    );
    notifyListeners();
  }

  Future<void> clearCart() async {
    if (mutating) return;
    mutating = true;
    notifyListeners();
    try {
      if (_useLocalMutations) {
        cart = Cart.empty();
        localOnly = true;
        message = 'Cart cleared (saved on this device)';
        await _persistCart();
        return;
      }
      try {
        cart = await _api.sendData(
          'DELETE',
          '/cart',
          map: (data) => Cart.fromJson(Map<String, dynamic>.from(data as Map)),
        );
        error = null;
        localOnly = false;
        message = 'Cart cleared';
        await _persistCart();
      } catch (e) {
        if (_isTransportOrServer(e)) {
          cart = Cart.empty();
          localOnly = true;
          message = 'Cart cleared (saved on this device)';
          await _persistCart();
          return;
        }
        if (e is ApiException) message = e.message;
        rethrow;
      }
    } finally {
      mutating = false;
      notifyListeners();
    }
  }

  Future<void> moveToWishlist(int itemId) async {
    if (mutating) return;
    if (_useLocalMutations || localOnly) {
      message = 'Reconnect to move items to your wishlist on the server.';
      notifyListeners();
      throw ApiException(message!, statusCode: null);
    }
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

  bool get _useLocalMutations =>
      localOnly ||
      _offline?.mode == MockDataMode.mockOnly ||
      _offline?.mode == MockDataMode.offlineSimulation ||
      _offline?.simulation == NetworkSimulation.offline ||
      _offline?.simulation == NetworkSimulation.apiError ||
      _offline?.simulation == NetworkSimulation.timeout;

  Future<void> _localAdd(int productId, {int quantity = 1}) async {
    final product = await _catalog?.productById(productId);
    final name = product?.name ?? 'Product $productId';
    final price = product?.price ?? 0;
    final slug = product?.slug;
    final thumb = product?.thumbnailUrl;
    final stock = product?.stockStatus ?? 'in_stock';

    final items = [...cart.items];
    final idx = items.indexWhere((i) => i.productId == productId);
    if (idx >= 0) {
      final cur = items[idx];
      final qty = cur.quantity + quantity;
      items[idx] = CartItem(
        id: cur.id,
        productId: cur.productId,
        name: cur.name,
        unitPrice: cur.unitPrice,
        quantity: qty,
        lineTotal: cur.unitPrice * qty,
        variantId: cur.variantId,
        thumbnailUrl: cur.thumbnailUrl,
        slug: cur.slug,
        stockStatus: cur.stockStatus,
        maxQty: cur.maxQty,
      );
    } else {
      items.add(
        CartItem(
          id: 900000 + productId,
          productId: productId,
          name: name,
          unitPrice: price,
          quantity: quantity,
          lineTotal: price * quantity,
          thumbnailUrl: thumb,
          slug: slug,
          stockStatus: stock,
          maxQty: 10,
        ),
      );
    }
    cart = _rebuild(items);
    localOnly = true;
    message = 'Added to cart (saved on this device)';
    error = null;
    _offline?.markSource(DataSourceKind.mock);
    await _persistCart();
    notifyListeners();
  }

  Future<void> _localUpdate(int itemId, int quantity) async {
    if (quantity <= 0) {
      await _localRemove(itemId);
      return;
    }
    final items = cart.items.map((i) {
      if (i.id != itemId) return i;
      return CartItem(
        id: i.id,
        productId: i.productId,
        name: i.name,
        unitPrice: i.unitPrice,
        quantity: quantity,
        lineTotal: i.unitPrice * quantity,
        variantId: i.variantId,
        thumbnailUrl: i.thumbnailUrl,
        slug: i.slug,
        stockStatus: i.stockStatus,
        maxQty: i.maxQty,
      );
    }).toList();
    cart = _rebuild(items);
    localOnly = true;
    error = null;
    await _persistCart();
  }

  Future<void> _localRemove(int itemId) async {
    final items = cart.items.where((i) => i.id != itemId).toList();
    cart = _rebuild(items);
    localOnly = true;
    error = null;
    await _persistCart();
  }

  Cart _rebuild(List<CartItem> items) {
    final subtotal = items.fold<double>(0, (s, i) => s + i.lineTotal);
    return Cart(
      items: items,
      itemCount: items.fold<int>(0, (s, i) => s + i.quantity),
      subtotal: subtotal,
      discountTotal: 0,
      grandTotal: subtotal,
      taxTotal: 0,
      shippingTotal: 0,
      couponCode: null,
      currency: 'INR',
      freeDelivery: FreeDelivery(
        enabled: true,
        threshold: 999,
        remaining: (999 - subtotal).clamp(0, 999),
        qualifies: subtotal >= 999,
      ),
      warnings: const [],
      checkoutBlocked: false,
    );
  }

  Future<void> _persistCart() async {
    await _local.writeCartJson({
      'items': cart.items
          .map(
            (i) => {
              'id': i.id,
              'product_id': i.productId,
              'variant_id': i.variantId,
              'name': i.name,
              'unit_price': i.unitPrice,
              'quantity': i.quantity,
              'line_total': i.lineTotal,
              'thumbnail_url': i.thumbnailUrl,
              'slug': i.slug,
              'stock_status': i.stockStatus,
              'max_qty': i.maxQty,
            },
          )
          .toList(),
      'item_count': cart.itemCount,
      'subtotal': cart.subtotal,
      'discount_total': cart.discountTotal,
      'tax_total': cart.taxTotal,
      'shipping_total': cart.shippingTotal,
      'grand_total': cart.grandTotal,
      'coupon_code': cart.couponCode,
      'currency': cart.currency,
      'free_delivery': {
        'enabled': cart.freeDelivery.enabled,
        'threshold': cart.freeDelivery.threshold,
        'remaining': cart.freeDelivery.remaining,
        'qualifies': cart.freeDelivery.qualifies,
      },
      'warnings': [],
      'checkout_blocked': cart.checkoutBlocked,
    });
  }

  bool _isTransportOrServer(Object e) {
    if (e is DioException) {
      final c = classifyDioException(e);
      return c.kind == NetworkKind.offline ||
          c.kind == NetworkKind.apiUnavailable ||
          c.kind == NetworkKind.apiTimeout ||
          c.kind == NetworkKind.serverError;
    }
    if (e is ApiException) {
      final code = e.statusCode;
      if (code == null) return true;
      return code >= 500 || code == 408 || code == 429;
    }
    return true;
  }
}
