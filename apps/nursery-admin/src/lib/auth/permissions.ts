import type { AdminUser } from "@/lib/types";

const STAFF_ROLES = new Set([
  "super_admin",
  "admin",
  "nursery_manager",
  "inventory_manager",
  "sales_manager",
  "order_manager",
  "delivery_manager",
  "customer_support",
  "content_manager",
  "accountant",
]);

export function isStaffUser(user: Pick<AdminUser, "roles" | "permissions"> | null | undefined) {
  if (!user) return false;
  if (user.roles?.some((r) => STAFF_ROLES.has(r))) return true;
  return (user.permissions?.length ?? 0) > 0;
}

export function isSuperAdmin(user: Pick<AdminUser, "roles"> | null | undefined) {
  return Boolean(user?.roles?.includes("super_admin"));
}

export function hasPermission(
  user: Pick<AdminUser, "roles" | "permissions"> | null | undefined,
  permission: string | string[],
) {
  if (!user) return false;
  if (isSuperAdmin(user)) return true;
  const needed = Array.isArray(permission) ? permission : [permission];
  const owned = new Set(user.permissions ?? []);
  return needed.some((p) => owned.has(p));
}

/** Require every listed permission (UX gate only — API still enforces). */
export function hasAllPermissions(
  user: Pick<AdminUser, "roles" | "permissions"> | null | undefined,
  permissions: string[],
) {
  if (!user) return false;
  if (isSuperAdmin(user)) return true;
  const owned = new Set(user.permissions ?? []);
  return permissions.every((p) => owned.has(p));
}

export function permissionDeniedMessage(permission: string | string[]) {
  const needed = Array.isArray(permission) ? permission.join(" or ") : permission;
  return `You do not have permission for this action (${needed}). Contact a Super Admin if you need access.`;
}

export function primaryRoleLabel(user: Pick<AdminUser, "roles"> | null | undefined) {
  if (!user?.roles?.length) return "Staff";
  const preferred = [
    "super_admin",
    "admin",
    "order_manager",
    "inventory_manager",
    "nursery_manager",
  ];
  const hit = preferred.find((r) => user.roles.includes(r)) ?? user.roles[0];
  return hit
    .split("_")
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join(" ");
}
