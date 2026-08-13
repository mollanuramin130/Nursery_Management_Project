/// QA-22 notification deep links (Customer Mobile).
library;

String? notificationDeepLink(Map<String, dynamic>? data) {
  if (data == null) return null;
  final route = data['route']?.toString();
  if (route != null && RegExp(r'^/[A-Za-z0-9/_-]*$').hasMatch(route)) {
    // Normalize customer web-style / admin-style routes to mobile routes.
    if (route.startsWith('/account/orders/')) {
      return route.replaceFirst('/account/orders/', '/orders/');
    }
    // QA-36-005: bare /returns/* → customer returns UI.
    if (route.startsWith('/returns')) {
      return route.replaceFirst('/returns', '/account/returns');
    }
    if (route.startsWith('/account/returns/')) {
      return route;
    }
    return route;
  }
  final orderId = data['order_id'];
  if (orderId != null) return '/orders/$orderId';
  final returnId = data['return_id'];
  if (returnId != null) return '/account/returns/$returnId';
  final productSlug = data['product_slug']?.toString();
  if (productSlug != null && productSlug.isNotEmpty) return '/product/$productSlug';
  final campaign = (data['campaign_slug'] ?? data['slug'])?.toString();
  if (campaign != null && campaign.isNotEmpty) return '/campaigns/$campaign';
  return null;
}

String? adminNotificationDeepLink(Map<String, dynamic>? data) {
  if (data == null) return null;
  final route = data['route']?.toString();
  if (route != null && RegExp(r'^/[A-Za-z0-9/_-]*$').hasMatch(route)) {
    return route;
  }
  final orderId = data['order_id'];
  if (orderId != null) return '/orders/$orderId';
  final returnId = data['return_id'];
  if (returnId != null) return '/returns/$returnId';
  return null;
}
