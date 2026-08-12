import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';

/// Shared catalog listing state: search, filters, sort, pagination.
class CatalogProvider extends ChangeNotifier {
  CatalogProvider(this._api);

  final ApiClient _api;

  CatalogFilters filters = const CatalogFilters();
  List<ProductSummary> items = [];
  int page = 1;
  int? total;
  bool loading = false;
  bool loadingMore = false;
  bool hasMore = true;
  String? error;

  int _requestId = 0;

  Future<void> applyFilters(CatalogFilters next) async {
    filters = next;
    await load(reset: true);
  }

  Future<void> load({required bool reset}) async {
    final requestId = ++_requestId;

    if (reset) {
      loading = true;
      error = null;
      page = 1;
      hasMore = true;
      notifyListeners();
    } else {
      if (!hasMore || loadingMore || loading) return;
      loadingMore = true;
      notifyListeners();
    }

    final nextPage = reset ? 1 : page + 1;
    try {
      final q = filters.q?.trim();
      final path = (q != null && q.isNotEmpty) ? '/search' : '/products';
      final result = await _api.getDataWithMeta(
        path,
        query: filters.toApiQuery(page: nextPage),
        map: (data) {
          if (data is List) {
            return data
                .whereType<Map>()
                .map(
                  (e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)),
                )
                .toList();
          }
          final map = Map<String, dynamic>.from(data as Map);
          final list = (map['products'] ?? map['items'] ?? []) as List;
          return list
              .whereType<Map>()
              .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
              .toList();
        },
      );

      if (requestId != _requestId) return;

      final pagination = result.meta?['pagination'];
      int? lastPage;
      if (pagination is Map) {
        total = (pagination['total'] as num?)?.toInt();
        lastPage = (pagination['last_page'] as num?)?.toInt();
      }

      page = nextPage;
      if (reset) {
        items = result.data;
      } else {
        final existing = items.map((e) => e.id).toSet();
        items = [
          ...items,
          ...result.data.where((p) => !existing.contains(p.id)),
        ];
      }
      hasMore = lastPage != null
          ? nextPage < lastPage
          : result.data.length >= 24;
      loading = false;
      loadingMore = false;
      error = null;
      notifyListeners();
    } catch (e) {
      if (requestId != _requestId) return;
      loading = false;
      loadingMore = false;
      error = e.toString();
      notifyListeners();
    }
  }
}
