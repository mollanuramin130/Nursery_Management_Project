class AdminUser {
  AdminUser({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    required this.permissions,
    this.status,
    this.phone,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;
  final String? status;
  final String? phone;

  factory AdminUser.fromJson(Map<String, dynamic> json) {
    return AdminUser(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      roles: (json['roles'] as List? ?? [])
          .map((e) => e.toString())
          .toList(),
      permissions: (json['permissions'] as List? ?? [])
          .map((e) => e.toString())
          .toList(),
      status: json['status']?.toString(),
      phone: json['phone']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'roles': roles,
        'permissions': permissions,
        'status': status,
        'phone': phone,
      };

  bool get isSuperAdmin => roles.contains('super_admin');

  bool can(String permission) =>
      isSuperAdmin || permissions.contains(permission);

  bool canAny(List<String> needed) =>
      isSuperAdmin || needed.any(permissions.contains);
}

class OrderSummary {
  OrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.grandTotal,
    this.paymentStatus,
    this.customerName,
    this.customerEmail,
    this.placedAt,
  });

  final int id;
  final String orderNumber;
  final String status;
  final double grandTotal;
  final String? paymentStatus;
  final String? customerName;
  final String? customerEmail;
  final String? placedAt;

  factory OrderSummary.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'];
    return OrderSummary(
      id: (json['id'] as num).toInt(),
      orderNumber: (json['order_number'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      grandTotal: (json['grand_total'] as num?)?.toDouble() ?? 0,
      paymentStatus: json['payment_status']?.toString(),
      customerName: customer is Map ? customer['name']?.toString() : null,
      customerEmail: customer is Map ? customer['email']?.toString() : null,
      placedAt: json['placed_at']?.toString(),
    );
  }
}

class InventoryRow {
  InventoryRow({
    required this.id,
    required this.productId,
    required this.productName,
    required this.sku,
    required this.qtyOnHand,
    required this.qtyReserved,
    required this.sellable,
    required this.stockStatus,
    this.warehouseCode,
    this.warehouseId,
    this.qtyDamaged,
    this.threshold,
    this.thumbnailUrl,
  });

  final int id;
  final int productId;
  final String productName;
  final String sku;
  final int qtyOnHand;
  final int qtyReserved;
  final int sellable;
  final String stockStatus;
  final String? warehouseCode;
  final int? warehouseId;
  final int? qtyDamaged;
  final int? threshold;
  final String? thumbnailUrl;

  factory InventoryRow.fromJson(Map<String, dynamic> json) {
    return InventoryRow(
      id: (json['id'] as num).toInt(),
      productId: (json['product_id'] as num).toInt(),
      productName: (json['product_name'] ?? '').toString(),
      sku: (json['sku'] ?? '').toString(),
      qtyOnHand: (json['qty_on_hand'] as num?)?.toInt() ?? 0,
      qtyReserved: (json['qty_reserved'] as num?)?.toInt() ?? 0,
      sellable: (json['sellable'] as num?)?.toInt() ??
          (json['quantity_available'] as num?)?.toInt() ??
          0,
      stockStatus: (json['stock_status'] ?? 'IN_STOCK').toString(),
      warehouseCode: json['warehouse_code']?.toString(),
      warehouseId: (json['warehouse_id'] as num?)?.toInt(),
      qtyDamaged: (json['qty_damaged'] as num?)?.toInt(),
      threshold: (json['low_stock_threshold'] as num?)?.toInt(),
      thumbnailUrl: json['thumbnail_url']?.toString(),
    );
  }
}

class PurchaseOrderSummary {
  PurchaseOrderSummary({
    required this.id,
    required this.poNumber,
    required this.status,
    required this.grandTotal,
    this.supplier,
    this.expectedAt,
    this.unitsOrdered,
    this.unitsReceived,
  });

  final int id;
  final String poNumber;
  final String status;
  final double grandTotal;
  final String? supplier;
  final String? expectedAt;
  final int? unitsOrdered;
  final int? unitsReceived;

  factory PurchaseOrderSummary.fromJson(Map<String, dynamic> json) {
    return PurchaseOrderSummary(
      id: (json['id'] as num).toInt(),
      poNumber: (json['po_number'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      grandTotal: (json['grand_total'] as num?)?.toDouble() ?? 0,
      supplier: json['supplier']?.toString(),
      expectedAt: json['expected_at']?.toString(),
      unitsOrdered: (json['units_ordered'] as num?)?.toInt(),
      unitsReceived: (json['units_received'] as num?)?.toInt(),
    );
  }
}
