import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/offline_controller.dart';

/// Tracks wishlist product IDs for heart sync across catalog/PDP.
class WishlistProvider extends ChangeNotifier {
  WishlistProvider(
    this._api, {
    OfflineController? offline,
    OfflineLocalStore? local,
  }) : _offline = offline,
       _local = local ?? OfflineLocalStore();

  final ApiClient _api;
  final OfflineController? _offline;
  final OfflineLocalStore _local;

  final Set<int> _ids = {};
  bool loading = false;
  bool _bootstrapped = false;
  bool localOnly = false;

  bool get bootstrapped => _bootstrapped;
  Set<int> get ids => _ids;
  bool contains(int productId) => _ids.contains(productId);

  Future<void> bootstrap({required bool signedIn}) async {
    if (!signedIn) {
      // Guest: restore device snapshot only (null = never written).
      final snap = await _local.readWishlistIdsSnapshot();
      if (snap != null) {
        _ids
          ..clear()
          ..addAll(snap);
        localOnly = true;
      }
      _bootstrapped = true;
      notifyListeners();
      return;
    }
    loading = true;
    notifyListeners();

    final forceLocal = _offline?.mode == MockDataMode.mockOnly ||
        _offline?.mode == MockDataMode.offlineSimulation ||
        _offline?.simulation == NetworkSimulation.offline ||
        _offline?.simulation == NetworkSimulation.apiError ||
        _offline?.simulation == NetworkSimulation.timeout;

    if (!forceLocal) {
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
        localOnly = false;
        await _local.writeWishlistIds(_ids);
        loading = false;
        _bootstrapped = true;
        notifyListeners();
        return;
      } catch (_) {
        // Keep previous / local on transient failure.
      }
    }

    final snap = await _local.readWishlistIdsSnapshot();
    if (snap != null) {
      // Respect empty snapshot — user may have removed all offline.
      _ids
        ..clear()
        ..addAll(snap);
      localOnly = true;
    } else if (forceLocal) {
      // Seed mock wishlist ONLY once on a fresh device.
      _ids
        ..clear()
        ..addAll({101, 201, 107});
      localOnly = true;
      await _local.writeWishlistIds(_ids);
    }
    loading = false;
    _bootstrapped = true;
    notifyListeners();
  }

  Future<void> add(int productId) async {
    await toggle(productId); // uses optimistic path
  }

  /// Optimistic remove — UI can drop the row immediately; restore on failure.
  Future<void> remove(int productId) async {
    final had = _ids.contains(productId);
    if (had) {
      _ids.remove(productId);
      notifyListeners();
      await _local.writeWishlistIds(_ids);
    }
    if (_useLocalMutations) {
      localOnly = true;
      return;
    }
    try {
      await _api.sendData('DELETE', '/wishlist/$productId', map: (_) => null);
      localOnly = false;
    } catch (e) {
      if (_isTransportOrServer(e)) {
        localOnly = true;
        return;
      }
      if (had) {
        _ids.add(productId);
        notifyListeners();
        await _local.writeWishlistIds(_ids);
      }
      rethrow;
    }
  }

  Future<bool> toggle(int productId) async {
    final wasSaved = _ids.contains(productId);
    if (wasSaved) {
      _ids.remove(productId);
      notifyListeners();
      await _local.writeWishlistIds(_ids);
      if (_useLocalMutations) {
        localOnly = true;
        return false;
      }
      try {
        await _api.sendData('DELETE', '/wishlist/$productId', map: (_) => null);
        localOnly = false;
        return false;
      } catch (e) {
        if (_isTransportOrServer(e)) {
          localOnly = true;
          return false;
        }
        _ids.add(productId);
        notifyListeners();
        await _local.writeWishlistIds(_ids);
        rethrow;
      }
    }
    _ids.add(productId);
    notifyListeners();
    await _local.writeWishlistIds(_ids);
    if (_useLocalMutations) {
      localOnly = true;
      return true;
    }
    try {
      await _api.sendData(
        'POST',
        '/wishlist',
        body: {'product_id': productId},
        map: (_) => null,
      );
      localOnly = false;
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 409) {
        return true;
      }
      if (_isTransportOrServer(e)) {
        localOnly = true;
        return true;
      }
      _ids.remove(productId);
      notifyListeners();
      await _local.writeWishlistIds(_ids);
      rethrow;
    } catch (e) {
      if (_isTransportOrServer(e)) {
        localOnly = true;
        return true;
      }
      _ids.remove(productId);
      notifyListeners();
      await _local.writeWishlistIds(_ids);
      rethrow;
    }
  }

  /// Atomically moves wishlist product into cart. Returns updated cart payload.
  Future<Cart> moveToCart(int productId, {int quantity = 1}) async {
    if (_useLocalMutations || localOnly) {
      throw ApiException(
        'Reconnect to move wishlist items into your server cart.',
        statusCode: null,
      );
    }
    final data = await _api.sendData(
      'POST',
      '/wishlist/$productId/move-to-cart',
      body: {'quantity': quantity},
      map: (raw) => Map<String, dynamic>.from(raw as Map),
    );
    _ids.remove(productId);
    notifyListeners();
    await _local.writeWishlistIds(_ids);
    final cartJson = data['cart'];
    if (cartJson is Map) {
      return Cart.fromJson(Map<String, dynamic>.from(cartJson));
    }
    throw ApiException('Invalid move-to-cart response', statusCode: 500);
  }

  void clearLocal() {
    _ids.clear();
    localOnly = false;
    notifyListeners();
    // Keep device offline hearts? Session expiry should clear server hearts;
    // also clear persisted to avoid cross-account bleed.
    unawaited(_local.writeWishlistIds({}));
  }

  bool get _useLocalMutations =>
      localOnly ||
      _offline?.mode == MockDataMode.mockOnly ||
      _offline?.mode == MockDataMode.offlineSimulation ||
      _offline?.simulation == NetworkSimulation.offline ||
      _offline?.simulation == NetworkSimulation.apiError ||
      _offline?.simulation == NetworkSimulation.timeout;

  /// Keep local optimistic state only for true connectivity loss — not HTTP 5xx.
  bool _isTransportOrServer(Object e) {
    if (e is DioException) {
      final c = classifyDioException(e);
      return c.kind == NetworkKind.offline ||
          c.kind == NetworkKind.apiUnavailable ||
          c.kind == NetworkKind.apiTimeout;
    }
    if (e is ApiException) {
      // Null status ≈ transport mapped by callers; 408 timeout only.
      return e.statusCode == null || e.statusCode == 408;
    }
    // Unknown non-API errors (socket, etc.) → keep local.
    return true;
  }
}
