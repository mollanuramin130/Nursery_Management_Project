/// Staff roles used by Admin Web / Mobile (must match backend RolePermissionSeeder).
const staffRoles = {
  'super_admin',
  'admin',
  'nursery_manager',
  'inventory_manager',
  'sales_manager',
  'order_manager',
  'delivery_manager',
  'customer_support',
  'content_manager',
  'accountant',
};

bool isStaffUser({
  required List<String> roles,
  required List<String> permissions,
}) {
  if (roles.any(staffRoles.contains)) return true;
  return permissions.isNotEmpty && !roles.contains('customer');
}

bool hasPermission(
  List<String> owned,
  List<String> roles,
  String permission,
) {
  if (roles.contains('super_admin')) return true;
  return owned.contains(permission);
}

bool hasAnyPermission(
  List<String> owned,
  List<String> roles,
  List<String> needed,
) {
  if (roles.contains('super_admin')) return true;
  return needed.any(owned.contains);
}
