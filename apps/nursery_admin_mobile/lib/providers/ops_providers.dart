import 'package:flutter/foundation.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/models/models.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';

class DashboardProvider extends ChangeNotifier {
  DashboardProvider(this._api, this._auth);

  final ApiClient _api;
  final AuthProvider _auth;

  bool loading = false;
  String? error;
  Map<String, dynamic>? inventoryDash;
  List<OrderSummary> recentOrders = [];
  int? pendingOrders;
  int? lowStock;
  int? outOfStock;
  int? pendingPos;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      if (_auth.can('inventory.view')) {
        inventoryDash = await _api.getData(
          '/admin/inventory/dashboard',
          map: (d) => Map<String, dynamic>.from(d as Map),
        );
        lowStock = (inventoryDash?['low_stock_count'] as num?)?.toInt();
        outOfStock = (inventoryDash?['out_of_stock_count'] as num?)?.toInt();
        pendingPos =
            (inventoryDash?['pending_purchase_orders'] as num?)?.toInt();
      }
      if (_auth.can('orders.view')) {
        final result = await _api.getDataWithMeta(
          '/admin/orders',
          query: {'per_page': 8, 'status': 'CONFIRMED'},
          map: (d) => (d as List)
              .map((e) => OrderSummary.fromJson(Map<String, dynamic>.from(e as Map)))
              .toList(),
        );
        recentOrders = result.data;
        pendingOrders =
            (result.meta?['pagination'] as Map?)?['total'] as int? ??
                recentOrders.length;
      }
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }
}

class OrdersProvider extends ChangeNotifier {
  OrdersProvider(this._api);

  final ApiClient _api;

  bool loading = false;
  String? error;
  List<OrderSummary> orders = [];
  int page = 1;
  int lastPage = 1;
  String q = '';
  String status = '';
  Map<String, dynamic>? detail;

  Future<void> load({bool reset = false}) async {
    if (reset) page = 1;
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _api.getDataWithMeta(
        '/admin/orders',
        query: {
          'page': page,
          'per_page': 20,
          if (q.trim().isNotEmpty) 'q': q.trim(),
          if (status.isNotEmpty) 'status': status,
        },
        map: (d) => (d as List)
            .map((e) => OrderSummary.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
      orders = result.data;
      final pag = result.meta?['pagination'] as Map?;
      lastPage = (pag?['last_page'] as num?)?.toInt() ?? 1;
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  Future<void> loadDetail(int id) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      detail = await _api.getData(
        '/admin/orders/$id',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
      detail = null;
    }
    loading = false;
    notifyListeners();
  }

  Future<String?> updateStatus(int id, String status, {String? note}) async {
    try {
      detail = await _api.sendData(
        'POST',
        '/admin/orders/$id/status',
        body: {
          'status': status,
          if (note != null && note.isNotEmpty) 'note': note,
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      notifyListeners();
      return null;
    } catch (e) {
      return e is ApiException ? e.userMessage : e.toString();
    }
  }
}

class InventoryProvider extends ChangeNotifier {
  InventoryProvider(this._api);

  final ApiClient _api;

  bool loading = false;
  String? error;
  List<InventoryRow> rows = [];
  InventoryRow? selected;
  Map<String, dynamic>? selectedDetail;
  List<Map<String, dynamic>> movements = [];
  String q = '';
  bool lowOnly = false;

  Future<void> load({bool lowStock = false}) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _api.getDataWithMeta(
        '/admin/inventory',
        query: {
          'per_page': 50,
          if (q.trim().isNotEmpty) 'q': q.trim(),
          if (lowStock || lowOnly) 'low_stock': true,
        },
        map: (d) => (d as List)
            .map((e) => InventoryRow.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
      rows = result.data;
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  Future<void> loadItem(int id) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      selectedDetail = await _api.getData(
        '/admin/inventory/$id',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      selected = InventoryRow.fromJson(selectedDetail!);
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  Future<void> loadMovements({int? productId}) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _api.getDataWithMeta(
        '/admin/inventory/movements',
        query: {
          'per_page': 40,
          if (productId != null) 'product_id': productId,
        },
        map: (d) => (d as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
      movements = result.data;
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  /// Returns null on success, error message on failure. Uses server response for final stock.
  Future<String?> adjust({
    required int warehouseId,
    required int productId,
    required int adjustment,
    required String reason,
    String? note,
    String? idempotencyKey,
  }) async {
    try {
      await _api.sendData(
        'POST',
        '/admin/inventory/adjust',
        body: {
          'warehouse_id': warehouseId,
          'product_id': productId,
          'adjustment': adjustment,
          'reason': reason,
          if (note != null && note.isNotEmpty) 'note': note,
          if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      return null;
    } catch (e) {
      return e is ApiException ? e.userMessage : e.toString();
    }
  }

  Future<InventoryRow?> findBySku(String sku) async {
    final result = await _api.getDataWithMeta(
      '/admin/inventory',
      query: {'q': sku, 'per_page': 10},
      map: (d) => (d as List)
          .map((e) => InventoryRow.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
    final exact = result.data.where(
      (r) => r.sku.toLowerCase() == sku.toLowerCase(),
    );
    if (exact.isNotEmpty) return exact.first;
    return result.data.isEmpty ? null : result.data.first;
  }
}

class PurchasingProvider extends ChangeNotifier {
  PurchasingProvider(this._api);

  final ApiClient _api;

  bool loading = false;
  String? error;
  List<PurchaseOrderSummary> orders = [];
  Map<String, dynamic>? detail;
  List<Map<String, dynamic>> suppliers = [];
  List<Map<String, dynamic>> warehouses = [];

  Future<void> loadPos({String? status}) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _api.getDataWithMeta(
        '/admin/purchase-orders',
        query: {
          'per_page': 30,
          if (status != null && status.isNotEmpty) 'status': status,
        },
        map: (d) => (d as List)
            .map((e) =>
                PurchaseOrderSummary.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
      orders = result.data;
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  Future<void> loadPo(int id) async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      detail = await _api.getData(
        '/admin/purchase-orders/$id',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
      detail = null;
    }
    loading = false;
    notifyListeners();
  }

  Future<String?> receive(int poId, List<Map<String, dynamic>> items) async {
    try {
      detail = await _api.sendData(
        'POST',
        '/admin/purchase-orders/$poId/receive',
        body: {'items': items},
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      notifyListeners();
      return null;
    } catch (e) {
      return e is ApiException ? e.userMessage : e.toString();
    }
  }

  Future<void> loadSuppliers() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      suppliers = await _api.getData(
        '/admin/suppliers',
        map: (d) => (d as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }

  Future<void> loadWarehouses() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      warehouses = await _api.getData(
        '/admin/warehouses',
        map: (d) => (d as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
    }
    loading = false;
    notifyListeners();
  }
}
