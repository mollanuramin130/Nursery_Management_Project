import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:nursery_app/data/cache_envelope.dart';
import 'package:nursery_app/data/mock_data_mode.dart';

/// Persists safe offline UI state with QA-38 cache envelopes.
/// Never stores API secrets. Mock must not overwrite API-origin envelopes.
class OfflineLocalStore {
  OfflineLocalStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _wishlistIds = 'gl_offline_wishlist_ids_v1';
  static const _wishlistInit = 'gl_offline_wishlist_init_v1';
  static const _cartJson = 'gl_offline_cart_json_v1';
  static const _ordersEnv = 'gl_offline_orders_env_v1';
  static const _catalogEnv = 'gl_offline_catalog_env_v1';
  static const _homeEnv = 'gl_offline_home_env_v1';
  // Legacy bare keys (migrate on read).
  static const _ordersJsonLegacy = 'gl_offline_orders_json_v1';
  static const _catalogJsonLegacy = 'gl_offline_catalog_json_v1';
  static const _homeJsonLegacy = 'gl_offline_home_json_v1';

  // --- Wishlist ---

  /// null = never persisted (may seed mock once); non-null = user/API snapshot.
  Future<Set<int>?> readWishlistIdsSnapshot() async {
    try {
      final init = await _storage.read(key: _wishlistInit);
      if (init != '1') return null;
      final raw = await _storage.read(key: _wishlistIds);
      if (raw == null || raw.isEmpty) return <int>{};
      final list = jsonDecode(raw) as List;
      return list.whereType<num>().map((e) => e.toInt()).toSet();
    } catch (_) {
      return null;
    }
  }

  Future<Set<int>> readWishlistIds() async {
    return await readWishlistIdsSnapshot() ?? {};
  }

  Future<void> writeWishlistIds(Set<int> ids) async {
    try {
      await _storage.write(key: _wishlistInit, value: '1');
      await _storage.write(
        key: _wishlistIds,
        value: jsonEncode(ids.toList()..sort()),
      );
    } catch (_) {}
  }

  // --- Cart (local mutations OK) ---

  Future<Map<String, dynamic>?> readCartJson() async {
    try {
      final raw = await _storage.read(key: _cartJson);
      if (raw == null || raw.isEmpty) return null;
      return Map<String, dynamic>.from(jsonDecode(raw) as Map);
    } catch (_) {
      return null;
    }
  }

  Future<void> writeCartJson(Map<String, dynamic> json) async {
    try {
      await _storage.write(key: _cartJson, value: jsonEncode(json));
    } catch (_) {}
  }

  // --- Enveloped catalog caches ---

  Future<CacheEnvelope?> readHomeEnvelope() async {
    return _readEnvelope(_homeEnv, legacyKey: _homeJsonLegacy);
  }

  Future<bool> writeHomeEnvelope(CacheEnvelope envelope) async {
    return _writeEnvelope(_homeEnv, envelope);
  }

  Future<Map<String, dynamic>?> readHomeJson() async {
    final env = await readHomeEnvelope();
    if (env?.data is Map) {
      return Map<String, dynamic>.from(env!.data as Map);
    }
    return null;
  }

  /// Legacy API — writes as API-origin only when [asApi] true.
  Future<void> writeHomeJson(
    Map<String, dynamic> json, {
    DataSourceKind source = DataSourceKind.remote,
  }) async {
    await writeHomeEnvelope(
      CacheEnvelope(
        data: json,
        source: source,
        cachedAt: DateTime.now(),
      ),
    );
  }

  Future<CacheEnvelope?> readCatalogEnvelope() async {
    return _readEnvelope(_catalogEnv, legacyKey: _catalogJsonLegacy, list: true);
  }

  Future<bool> writeCatalogEnvelope(CacheEnvelope envelope) async {
    return _writeEnvelope(_catalogEnv, envelope);
  }

  Future<List<dynamic>?> readCatalogJson() async {
    final env = await readCatalogEnvelope();
    if (env?.data is List) return env!.data as List;
    return null;
  }

  Future<void> writeCatalogJson(
    List<dynamic> json, {
    DataSourceKind source = DataSourceKind.remote,
  }) async {
    await writeCatalogEnvelope(
      CacheEnvelope(data: json, source: source, cachedAt: DateTime.now()),
    );
  }

  Future<CacheEnvelope?> readOrdersEnvelope() async {
    return _readEnvelope(_ordersEnv, legacyKey: _ordersJsonLegacy, list: true);
  }

  Future<bool> writeOrdersEnvelope(CacheEnvelope envelope) async {
    return _writeEnvelope(_ordersEnv, envelope);
  }

  Future<List<dynamic>?> readOrdersJson() async {
    final env = await readOrdersEnvelope();
    if (env?.data is List) return env!.data as List;
    return null;
  }

  Future<void> writeOrdersJson(
    List<dynamic> json, {
    DataSourceKind source = DataSourceKind.remote,
  }) async {
    await writeOrdersEnvelope(
      CacheEnvelope(data: json, source: source, cachedAt: DateTime.now()),
    );
  }

  Future<CacheEnvelope?> _readEnvelope(
    String key, {
    String? legacyKey,
    bool list = false,
  }) async {
    try {
      final raw = await _storage.read(key: key);
      if (raw != null && raw.isNotEmpty) {
        return CacheEnvelope.tryParse(jsonDecode(raw));
      }
      if (legacyKey != null) {
        final legacy = await _storage.read(key: legacyKey);
        if (legacy != null && legacy.isNotEmpty) {
          final decoded = jsonDecode(legacy);
          if (list && decoded is List) {
            return CacheEnvelope(
              data: decoded,
              source: DataSourceKind.cache,
              cachedAt: DateTime.fromMillisecondsSinceEpoch(0),
              version: 0,
            );
          }
          if (!list && decoded is Map) {
            return CacheEnvelope.tryParse(decoded);
          }
        }
      }
    } catch (_) {}
    return null;
  }

  Future<bool> _writeEnvelope(String key, CacheEnvelope incoming) async {
    try {
      final existing = await _readEnvelope(key);
      if (!CacheEnvelope.mayWrite(
        existing: existing,
        incoming: incoming.source,
      )) {
        return false;
      }
      await _storage.write(key: key, value: jsonEncode(incoming.toJson()));
      return true;
    } catch (_) {
      return false;
    }
  }
}
