"use client";

import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

export function PermissionGate({
  permission,
  children,
  fallback = null,
}: {
  permission: string | string[];
  children: React.ReactNode;
  fallback?: React.ReactNode;
}) {
  const user = useAuthStore((s) => s.user);
  if (!hasPermission(user, permission)) return <>{fallback}</>;
  return <>{children}</>;
}
