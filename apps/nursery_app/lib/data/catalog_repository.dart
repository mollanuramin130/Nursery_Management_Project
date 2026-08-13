import 'package:dio/dio.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/cache_envelope.dart';
import 'package:nursery_app/data/mock_asset_store.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';

/// QA-38 priority: API > fresh cache > stale cache > mock > empty.
/// Cache-first UI; mock never overwrites API-origin cache.
class CatalogRepository {
  CatalogRepository({
    required ApiClient api,
    required OfflineController offline,
    MockAssetStore? assets,
    OfflineLocalStore? local,
  }) : _api = api,
       _offline = offline,
       _assets = assets ?? MockAssetStore(),
       _local = local ?? OfflineLocalStore();

  final ApiClient _api;
  final OfflineController _offline;
  final MockAssetStore _assets;
  final OfflineLocalStore _local;

  /// In-flight /home coalescing (QA-38: no duplicate requests).
  Future<ResolvedResult<HomeFeed>>? _homeInFlight;

  // ---------- HOME ----------

  Future<ResolvedResult<HomeFeed>?> peekHome() async {
    final env = await _local.readHomeEnvelope();
    if (env != null && env.data is Map) {
      final feed = HomeFeed.fromJson(Map<String, dynamic>.from(env.data as Map));
      final src = env.readSource;
      _offline.markSource(src);
      return ResolvedResult(
        data: feed,
        source: src,
        stale: src != DataSourceKind.cache || !env.isFresh(),
        cachedAt: env.cachedAt,
      );
    }
    return null;
  }

  /// Cache-first then API. Prefer [onImmediate] for smooth UI.
  Future<ResolvedResult<HomeFeed>> getHome({
    void Function(ResolvedResult<HomeFeed> immediate)? onImmediate,
  }) async {
    if (_offline.mode == MockDataMode.mockOnly) {
      return _homeFromMock();
    }

    final peeked = await peekHome();
    if (peeked != null) {
      onImmediate?.call(peeked.copyWith(updating: !_shouldSkipRemote));
      if (_shouldSkipRemote) return peeked;
    }

    if (_shouldSkipRemote) {
      return peeked ?? await _homeFromMock();
    }

    _homeInFlight ??= _fetchHomeRemote().whenComplete(() => _homeInFlight = null);
    try {
      return await _homeInFlight!;
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        if (peeked != null) return peeked;
        rethrow;
      }
      return peeked ?? await _homeFromMock();
    }
  }

  Future<ResolvedResult<HomeFeed>> _fetchHomeRemote() async {
    late HomeFeed home;
    late Map<String, dynamic> raw;
    await _api.getData(
      '/home',
      map: (data) {
        raw = Map<String, dynamic>.from(data as Map);
        home = HomeFeed.fromJson(raw);
        return home;
      },
    );
    await _local.writeHomeEnvelope(
      CacheEnvelope(
        data: raw,
        source: DataSourceKind.remote,
        cachedAt: DateTime.now(),
      ),
    );
    _offline.markRemoteOk();
    return ResolvedResult(data: home, source: DataSourceKind.remote);
  }

  Future<ResolvedResult<HomeFeed>> _homeFromMock() async {
    final data = await _assets.dataOf('home.json');
    final map = Map<String, dynamic>.from(data as Map);
    final feed = HomeFeed.fromJson(map);
    // QA-38: do NOT write mock into API cache (mayWrite blocks if API exists).
    await _local.writeHomeEnvelope(
      CacheEnvelope(
        data: map,
        source: DataSourceKind.mock,
        cachedAt: DateTime.now(),
      ),
    );
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(
      data: feed,
      source: DataSourceKind.mock,
      stale: true,
    );
  }

  // ---------- CATEGORIES ----------

  Future<ResolvedResult<List<CategoryChip>>> getCategories() async {
    if (_offline.mode == MockDataMode.mockOnly) {
      return _categoriesFromMock();
    }
    try {
      if (!_shouldSkipRemote) {
        final cats = await _api.getData(
          '/categories',
          map: (data) => _mapCategories(data),
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: cats, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        rethrow;
      }
    }
    return _categoriesFromMock();
  }

  Future<ResolvedResult<List<CategoryChip>>> _categoriesFromMock() async {
    final data = await _assets.dataOf('categories.json');
    final cats = _mapCategories(data);
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(data: cats, source: DataSourceKind.mock, stale: true);
  }

  List<CategoryChip> _mapCategories(dynamic data) {
    return (data as List)
        .whereType<Map>()
        .map((e) {
          final m = Map<String, dynamic>.from(e);
          return CategoryChip(
            name: m['name'] as String,
            slug: m['slug'] as String,
            imageUrl: m['image_url'] as String?,
            parentId: m['parent_id'] as int?,
          );
        })
        .where((c) => c.parentId == null)
        .toList();
  }

  // ---------- PRODUCTS ----------

  Future<ResolvedResult<List<ProductSummary>>?> peekCatalog({
    CatalogFilters filters = const CatalogFilters(),
  }) async {
    final env = await _local.readCatalogEnvelope();
    if (env != null && env.data is List) {
      var list = (env.data as List)
          .whereType<Map>()
          .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      list = _filterLocal(list, filters);
      final src = env.readSource;
      _offline.markSource(src);
      return ResolvedResult(
        data: list,
        source: src,
        stale: !env.isFresh(),
        cachedAt: env.cachedAt,
      );
    }
    return null;
  }

  Future<ResolvedResult<List<ProductSummary>>> listProducts({
    CatalogFilters filters = const CatalogFilters(),
    int page = 1,
    void Function(ResolvedResult<List<ProductSummary>> immediate)? onImmediate,
  }) async {
    if (_offline.mode == MockDataMode.mockOnly) {
      return _productsFromMock(filters: filters, page: page);
    }

    final peeked = page == 1 ? await peekCatalog(filters: filters) : null;
    if (peeked != null) {
      onImmediate?.call(peeked.copyWith(updating: !_shouldSkipRemote));
      if (_shouldSkipRemote) return peeked;
    }

    try {
      if (!_shouldSkipRemote) {
        final q = filters.q?.trim();
        final path = (q != null && q.isNotEmpty) ? '/search' : '/products';
        final result = await _api.getDataWithMeta(
          path,
          query: filters.toApiQuery(page: page),
          map: (data) => _mapProductList(data),
        );
        if (page == 1) {
          await _local.writeCatalogEnvelope(
            CacheEnvelope(
              data: result.data.map(_productToCacheMap).toList(),
              source: DataSourceKind.remote,
              cachedAt: DateTime.now(),
            ),
          );
        }
        _offline.markRemoteOk();
        return ResolvedResult(data: result.data, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        if (peeked != null) return peeked;
        rethrow;
      }
    }

    if (peeked != null) return peeked;
    return _productsFromMock(filters: filters, page: page);
  }

  Future<ResolvedResult<List<ProductSummary>>> _productsFromMock({
    required CatalogFilters filters,
    required int page,
  }) async {
    final data = await _assets.dataOf('products.json');
    var list = _mapProductList(data);
    list = _filterLocal(list, filters);
    const perPage = 24;
    final start = (page - 1) * perPage;
    if (start >= list.length) {
      list = [];
    } else {
      final end = (start + perPage).clamp(0, list.length);
      list = list.sublist(start, end);
    }
    // Do not persist mock over API catalog.
    await _local.writeCatalogEnvelope(
      CacheEnvelope(
        data: list.map(_productToCacheMap).toList(),
        source: DataSourceKind.mock,
        cachedAt: DateTime.now(),
      ),
    );
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(data: list, source: DataSourceKind.mock, stale: true);
  }

  List<ProductSummary> _filterLocal(
    List<ProductSummary> list,
    CatalogFilters filters,
  ) {
    var out = list;
    final q = filters.q?.trim().toLowerCase();
    if (q != null && q.isNotEmpty) {
      out = out
          .where(
            (p) =>
                p.name.toLowerCase().contains(q) ||
                p.slug.toLowerCase().contains(q) ||
                (p.scientificName?.toLowerCase().contains(q) ?? false) ||
                (p.productType?.toLowerCase().contains(q) ?? false),
          )
          .toList();
    }
    final type = filters.productType?.trim().toLowerCase();
    if (type != null && type.isNotEmpty) {
      out = out
          .where((p) => (p.productType ?? '').toLowerCase() == type)
          .toList();
    }
    return out;
  }

  Future<ResolvedResult<ProductDetail>> getProduct(String slug) async {
    if (_offline.mode == MockDataMode.mockOnly) {
      return _productFromMock(slug);
    }
    try {
      if (!_shouldSkipRemote) {
        final product = await _api.getData(
          '/products/$slug',
          map: (data) =>
              ProductDetail.fromJson(Map<String, dynamic>.from(data as Map)),
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: product, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (e is ApiException && e.statusCode == 404) {
        try {
          return await _productFromMock(slug);
        } catch (_) {
          rethrow;
        }
      }
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        rethrow;
      }
    }
    return _productFromMock(slug);
  }

  Future<ResolvedResult<ProductDetail>> _productFromMock(String slug) async {
    final data = await _assets.dataOf('product_details.json');
    final map = Map<String, dynamic>.from(data as Map);
    final row = map[slug];
    if (row is! Map) {
      throw ApiException(
        'This information is no longer available.',
        statusCode: 404,
      );
    }
    final detail = ProductDetail.fromJson(Map<String, dynamic>.from(row));
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(
      data: detail,
      source: DataSourceKind.mock,
      stale: true,
    );
  }

  Future<ResolvedResult<List<ProductSummary>>> related(int productId) async {
    try {
      if (!_shouldSkipRemote && _offline.mode != MockDataMode.mockOnly) {
        final list = await _api.getData(
          '/products/$productId/related',
          map: (data) => _mapProductList(data),
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: list, source: DataSourceKind.remote);
      }
    } catch (_) {}
    final products = await _productsFromMock(
      filters: const CatalogFilters(),
      page: 1,
    );
    final related =
        products.data.where((p) => p.id != productId).take(4).toList();
    return ResolvedResult(
      data: related,
      source: DataSourceKind.mock,
      stale: true,
    );
  }

  Future<ResolvedResult<List<String>>> searchSuggestions(String q) async {
    final query = q.trim().toLowerCase();
    if (query.isEmpty) {
      return const ResolvedResult(data: [], source: DataSourceKind.mock);
    }

    try {
      if (!_shouldSkipRemote && _offline.mode != MockDataMode.mockOnly) {
        final list = await _api.getData(
          '/search/suggestions',
          query: {'q': q},
          map: (data) {
            if (data is List) {
              return data.map((e) => e.toString()).toList();
            }
            if (data is Map) {
              final s = data['suggestions'];
              if (s is List) {
                return s.map((e) {
                  if (e is Map && e['term'] != null) return e['term'].toString();
                  return e.toString();
                }).toList();
              }
            }
            return <String>[];
          },
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: list, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        rethrow;
      }
    }

    final data = await _assets.dataOf('search.json');
    final suggestions = (Map<String, dynamic>.from(data as Map)['suggestions']
                as List?)
            ?.whereType<Map>()
            .map((e) => e['term']?.toString() ?? '')
            .where(
              (t) =>
                  t.toLowerCase().contains(query) ||
                  query.contains(t.toLowerCase()),
            )
            .toList() ??
        [];
    final products = await _productsFromMock(
      filters: CatalogFilters(q: q),
      page: 1,
    );
    final names = products.data.map((p) => p.name).toList();
    final merged = <String>{...suggestions, ...names}.toList();
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(
      data: merged.take(8).toList(),
      source: DataSourceKind.mock,
      stale: true,
    );
  }

  Future<ResolvedResult<List<ProductSummary>>> wishlistProducts() async {
    try {
      if (!_shouldSkipRemote && _offline.mode != MockDataMode.mockOnly) {
        final rows = await _api.getData(
          '/wishlist',
          map: (data) => (data as List)
              .whereType<Map>()
              .map((e) {
                final product = e['product'];
                if (product is Map) {
                  return ProductSummary.fromJson(
                    Map<String, dynamic>.from(product),
                  );
                }
                return null;
              })
              .whereType<ProductSummary>()
              .toList(),
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: rows, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        rethrow;
      }
    }
    final data = await _assets.dataOf('wishlist.json');
    final rows = (data as List)
        .whereType<Map>()
        .map((e) {
          final product = e['product'];
          if (product is Map) {
            return ProductSummary.fromJson(Map<String, dynamic>.from(product));
          }
          return null;
        })
        .whereType<ProductSummary>()
        .toList();
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(data: rows, source: DataSourceKind.mock, stale: true);
  }

  Future<ResolvedResult<List<OrderSummary>>> getOrders() async {
    final peekedEnv = await _local.readOrdersEnvelope();
    ResolvedResult<List<OrderSummary>>? peeked;
    if (peekedEnv != null && peekedEnv.data is List) {
      final rows = (peekedEnv.data as List)
          .whereType<Map>()
          .map((e) => OrderSummary.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      peeked = ResolvedResult(
        data: rows,
        source: peekedEnv.readSource,
        stale: !peekedEnv.isFresh(),
        cachedAt: peekedEnv.cachedAt,
      );
    }

    try {
      if (!_shouldSkipRemote && _offline.mode != MockDataMode.mockOnly) {
        final rows = await _api.getData(
          '/orders',
          map: (data) => (data as List)
              .whereType<Map>()
              .map((e) => OrderSummary.fromJson(Map<String, dynamic>.from(e)))
              .toList(),
        );
        await _local.writeOrdersEnvelope(
          CacheEnvelope(
            data: rows
                .map(
                  (o) => {
                    'id': o.id,
                    'order_number': o.orderNumber,
                    'status': o.status,
                    'grand_total': o.grandTotal,
                    'item_count': o.itemCount,
                    'created_at': o.createdAt,
                    'placed_at': o.placedAt,
                    'thumbnail': o.thumbnail,
                    'preview_name': o.previewName,
                    'estimated_delivery': o.estimatedDelivery,
                    'can_cancel': o.canCancel,
                    'can_reorder': o.canReorder,
                    'can_return': o.canReturn,
                    'payment_status': o.paymentStatus,
                    'currency': o.currency,
                  },
                )
                .toList(),
            source: DataSourceKind.remote,
            cachedAt: DateTime.now(),
          ),
        );
        _offline.markRemoteOk();
        return ResolvedResult(data: rows, source: DataSourceKind.remote);
      }
    } catch (e) {
      if (_offline.mode == MockDataMode.onlineOnly || !_isFallbackEligible(e)) {
        if (peeked != null) return peeked;
        rethrow;
      }
    }

    if (peeked != null) {
      _offline.markSource(peeked.source);
      return peeked;
    }

    final data = await _assets.dataOf('orders.json');
    final rows = (data as List)
        .whereType<Map>()
        .map((e) => OrderSummary.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    await _local.writeOrdersEnvelope(
      CacheEnvelope(
        data: data,
        source: DataSourceKind.mock,
        cachedAt: DateTime.now(),
      ),
    );
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(data: rows, source: DataSourceKind.mock, stale: true);
  }

  Future<ResolvedResult<Cart>> getMockCart() async {
    final data = await _assets.dataOf('cart.json');
    final cart = Cart.fromJson(Map<String, dynamic>.from(data as Map));
    _offline.markSource(DataSourceKind.mock);
    return ResolvedResult(data: cart, source: DataSourceKind.mock, stale: true);
  }

  Future<ProductSummary?> productById(int id) async {
    final peeked = await peekCatalog();
    if (peeked != null) {
      for (final p in peeked.data) {
        if (p.id == id) return p;
      }
    }
    final all = await _productsFromMock(
      filters: const CatalogFilters(),
      page: 1,
    );
    for (final p in all.data) {
      if (p.id == id) return p;
    }
    return null;
  }

  /// Soft refresh after reconnect — API wins; does not clear UI on failure.
  Future<void> syncAfterReconnect() async {
    if (_shouldSkipRemote) return;
    try {
      await getHome();
    } catch (_) {}
    try {
      await listProducts(filters: const CatalogFilters(), page: 1);
    } catch (_) {}
  }

  bool get _shouldSkipRemote =>
      _offline.mode == MockDataMode.mockOnly ||
      _offline.mode == MockDataMode.offlineSimulation ||
      _offline.simulation == NetworkSimulation.offline ||
      _offline.simulation == NetworkSimulation.apiError ||
      _offline.simulation == NetworkSimulation.timeout;

  bool _isFallbackEligible(Object e) {
    if (e is DioException) {
      final c = classifyDioException(e);
      return c.kind == NetworkKind.offline ||
          c.kind == NetworkKind.apiUnavailable ||
          c.kind == NetworkKind.apiTimeout ||
          c.kind == NetworkKind.serverError ||
          c.kind == NetworkKind.rateLimited;
    }
    if (e is ApiException) {
      final code = e.statusCode;
      if (code == null) return true;
      if (code == 401 || code == 403 || code == 422 || code == 409) return false;
      if (code == 404) return true;
      return code >= 500 || code == 408 || code == 429;
    }
    return true;
  }

  List<ProductSummary> _mapProductList(dynamic data) {
    if (data is List) {
      return data
          .whereType<Map>()
          .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    }
    final map = Map<String, dynamic>.from(data as Map);
    final list = (map['products'] ?? map['items'] ?? []) as List;
    return list
        .whereType<Map>()
        .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Map<String, dynamic> _productToCacheMap(ProductSummary p) => {
        'id': p.id,
        'name': p.name,
        'slug': p.slug,
        'price': p.price,
        'compare_at_price': p.compareAtPrice,
        'currency': p.currency,
        'thumbnail_url': p.thumbnailUrl,
        'product_type': p.productType,
        'rating_avg': p.ratingAvg,
        'rating_count': p.ratingCount,
        'stock_status': p.stockStatus,
        'badges': p.badges,
        if (p.scientificName != null)
          'plant': {'scientific_name': p.scientificName},
      };
}
