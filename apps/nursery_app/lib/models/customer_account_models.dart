// Customer account parity models (QA-PAR-001 / QA-PAR-002).
// Maps Laravel `GET /customer/returns` and `GET /customer/reviews` payloads.

class CustomerReturnSummary {
  CustomerReturnSummary({
    required this.id,
    required this.orderId,
    required this.status,
    this.orderNumber,
    this.notes,
    this.createdAt,
    this.items = const [],
  });

  final int id;
  final int orderId;
  final String status;
  final String? orderNumber;
  final String? notes;
  final String? createdAt;
  final List<CustomerReturnItem> items;

  String get statusLabel => status.replaceAll('_', ' ');

  factory CustomerReturnSummary.fromJson(Map<String, dynamic> json) {
    final itemsRaw = (json['items'] as List?) ?? const [];
    return CustomerReturnSummary(
      id: (json['id'] as num).toInt(),
      orderId: (json['order_id'] as num).toInt(),
      status: json['status']?.toString() ?? 'UNKNOWN',
      orderNumber: json['order_number']?.toString(),
      notes: json['notes']?.toString(),
      createdAt: json['created_at']?.toString(),
      items: itemsRaw
          .whereType<Map>()
          .map((e) => CustomerReturnItem.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}

class CustomerReturnItem {
  CustomerReturnItem({
    required this.id,
    required this.quantity,
    this.name,
    this.sku,
    this.reason,
  });

  final int id;
  final int quantity;
  final String? name;
  final String? sku;
  final String? reason;

  factory CustomerReturnItem.fromJson(Map<String, dynamic> json) =>
      CustomerReturnItem(
        id: (json['id'] as num).toInt(),
        quantity: (json['quantity'] as num?)?.toInt() ?? 0,
        name: json['name']?.toString(),
        sku: json['sku']?.toString(),
        reason: json['reason']?.toString(),
      );
}

class CustomerMyReview {
  CustomerMyReview({
    required this.id,
    required this.productId,
    required this.rating,
    required this.status,
    this.productName,
    this.productSlug,
    this.title,
    this.body,
    this.createdAt,
  });

  final int id;
  final int productId;
  final int rating;
  final String status;
  final String? productName;
  final String? productSlug;
  final String? title;
  final String? body;
  final String? createdAt;

  factory CustomerMyReview.fromJson(Map<String, dynamic> json) =>
      CustomerMyReview(
        id: (json['id'] as num).toInt(),
        productId: (json['product_id'] as num).toInt(),
        rating: (json['rating'] as num).toInt(),
        status: json['status']?.toString() ?? 'pending',
        productName: json['product_name']?.toString(),
        productSlug: json['product_slug']?.toString(),
        title: json['title']?.toString(),
        body: json['body']?.toString(),
        createdAt: json['created_at']?.toString(),
      );
}

/// Parses list payloads that may be a bare array or `{ data: [...] }`.
List<Map<String, dynamic>> parseAccountListPayload(dynamic data) {
  if (data is List) {
    return data
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }
  if (data is Map && data['data'] is List) {
    return (data['data'] as List)
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }
  return const [];
}
