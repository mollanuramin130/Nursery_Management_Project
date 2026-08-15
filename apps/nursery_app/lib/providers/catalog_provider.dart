import 'package:flutter/foundation.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/core/refresh_coalescer.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';

/// Shared catalog listing — cache-first, soft refresh (QA-38).
class CatalogProvider extends ChangeNotifier {
  CatalogProvider(this._repo);

  final CatalogRepository _repo;
  final RefreshCoalescer _resetRefresh = RefreshCoalescer();

  CatalogFilters filters = const CatalogFilters();
  List<ProductSummary> items = [];
  int page = 1;
  int? total;
  bool loading = false;
  bool loadingMore = false;
  bool hasMore = true;
  String? error;
  DataSourceKind? source;
  bool stale = false;
  bool softUpdating = false;

  int _requestId = 0;

  Future<void> applyFilters(CatalogFilters next) async {
    filters = next;
    await load(reset: true);
  }

  Future<void> load({required bool reset}) async {
    if (reset) {
      return _resetRefresh.run(() => _loadBody(reset: true));
    }
    return _loadBody(reset: false);
  }

  Future<void> _loadBody({required bool reset}) async {
    final requestId = ++_requestId;

    if (reset) {
      error = null;
      page = 1;
      hasMore = true;
      if (items.isEmpty) {
        loading = true;
      } else {
        softUpdating = true;
      }
      notifyListeners();

      // Cache-first paint.
      final peeked = await _repo.peekCatalog(filters: filters);
      if (requestId != _requestId) return;
      if (peeked != null && peeked.data.isNotEmpty) {
        items = peeked.data;
        source = peeked.source;
        stale = peeked.stale;
        loading = false;
        softUpdating = true;
        notifyListeners();
      }
    } else {
      if (!hasMore || loadingMore || loading) return;
      loadingMore = true;
      notifyListeners();
    }

    final nextPage = reset ? 1 : page + 1;
    try {
      final result = await _repo.listProducts(
        filters: filters,
        page: nextPage,
        onImmediate: (immediate) {
          if (requestId != _requestId || !reset) return;
          if (items.isNotEmpty) return;
          items = immediate.data;
          source = immediate.source;
          stale = immediate.stale;
          softUpdating = immediate.updating;
          loading = false;
          notifyListeners();
        },
      );

      if (requestId != _requestId) return;

      page = nextPage;
      source = result.source;
      stale = result.stale;
      if (reset) {
        items = result.data;
      } else {
        final existing = items.map((e) => e.id).toSet();
        items = [
          ...items,
          ...result.data.where((p) => !existing.contains(p.id)),
        ];
      }
      hasMore = result.source == DataSourceKind.remote
          ? result.data.length >= 24
          : false;
      total = items.length;
      loading = false;
      loadingMore = false;
      softUpdating = false;
      error = null;
      notifyListeners();
    } catch (e) {
      if (requestId != _requestId) return;
      loading = false;
      loadingMore = false;
      softUpdating = false;
      if (items.isEmpty) {
        error = e.toString();
      }
      notifyListeners();
    }
  }
}
