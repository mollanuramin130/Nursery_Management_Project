"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Sidebar } from "@/components/layout/Sidebar";
import { Topbar } from "@/components/layout/Topbar";
import { ToastViewport } from "@/components/feedback/ToastViewport";
import { useAuthStore } from "@/store/auth";

const titles: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/orders": "Orders",
  "/products": "Products",
  "/categories": "Categories",
  "/inventory": "Inventory",
  "/marketing": "Marketing",
  "/marketing/automations": "Automations",
  "/coupons": "Coupons",
  "/campaigns": "Campaigns",
  "/banners": "Banners",
  "/customers": "Customers",
  "/customers/segments": "Segments",
  "/users": "Users & Roles",
  "/refunds": "Refunds",
  "/suppliers": "Suppliers",
  "/settings": "Settings",
  "/audit-logs": "Audit logs",
  "/analytics": "Analytics",
  "/reports": "Reports",
  "/coming-soon": "Coming soon",
};

function resolveTitle(pathname: string) {
  if (pathname.startsWith("/analytics/")) return "Analytics";
  if (pathname.startsWith("/orders/")) return "Order detail";
  if (pathname.startsWith("/products/new")) return "Create product";
  if (pathname.includes("/edit")) return "Edit";
  if (pathname.startsWith("/customers/segments")) return "Segments";
  if (pathname.startsWith("/customers/")) return "Customer 360";
  if (pathname.startsWith("/users/new")) return "Create user";
  if (pathname.startsWith("/users/")) return "User detail";
  if (pathname.startsWith("/marketing/automations")) return "Automations";
  if (pathname.startsWith("/coupons/")) return "Coupon";
  if (pathname.startsWith("/campaigns/")) return "Campaign";
  if (pathname.startsWith("/banners/")) return "Banner";
  return titles[pathname] ?? "Admin";
}

export function AdminShell({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, bootstrapped, bootstrap } = useAuthStore();

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  useEffect(() => {
    if (!bootstrapped) return;
    if (!user) router.replace(`/login?next=${encodeURIComponent(pathname)}`);
  }, [bootstrapped, user, router, pathname]);

  if (!bootstrapped || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-[var(--admin-muted)]">
        Loading admin session…
      </div>
    );
  }

  return (
    <div className="flex min-h-screen bg-[var(--admin-bg)]">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar title={resolveTitle(pathname)} />
        <main className="flex-1 p-4 md:p-6">{children}</main>
      </div>
      <ToastViewport />
    </div>
  );
}
