/// Admin mobile notification deep links (QA-22 / QA-35).
library;

String? adminNotificationDeepLink(Map<String, dynamic>? data) {
  if (data == null) return null;

  final orderId = data['order_id'];
  final route = data['route']?.toString();

  // Admin Mobile has no returns module (QA-ADM-002). API often sends
  // route=/returns/{id} — never follow that path (QA-35-001).
  if (route != null && route.startsWith('/returns')) {
    if (orderId != null) return '/orders/$orderId';
    return '/orders';
  }

  if (route != null && RegExp(r'^/[A-Za-z0-9/_-]*$').hasMatch(route)) {
    return route;
  }
  if (orderId != null) return '/orders/$orderId';
  if (data['return_id'] != null) {
    return '/orders';
  }
  return null;
}
