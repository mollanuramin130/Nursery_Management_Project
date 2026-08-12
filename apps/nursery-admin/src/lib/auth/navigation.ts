import type { AdminUser } from "@/lib/types";
import { hasPermission } from "@/lib/auth/permissions";

export type NavItem = {
  label: string;
  href: string;
  permission?: string | string[];
};

export type NavSection = {
  label?: string;
  items: NavItem[];
};

export const NAV_SECTIONS: NavSection[] = [
  {
    items: [{ label: "Dashboard", href: "/dashboard", permission: "reports.view" }],
  },
  {
    label: "Commerce",
    items: [
      { label: "Orders", href: "/orders", permission: "orders.view" },
      { label: "Products", href: "/products", permission: "products.read" },
      { label: "Categories", href: "/categories", permission: "products.write" },
    ],
  },
  {
    label: "Inventory",
    items: [
      { label: "Inventory", href: "/inventory", permission: "inventory.view" },
      { label: "Movements", href: "/inventory/movements", permission: "inventory.view" },
      { label: "Transfers", href: "/inventory/transfers", permission: ["inventory.view", "inventory.transfer"] },
      { label: "Stock alerts", href: "/stock-alerts", permission: "inventory.view" },
      { label: "Reconciliation", href: "/inventory/reconciliation", permission: "inventory.adjust" },
      { label: "Warehouses", href: "/warehouses", permission: ["inventory.view", "warehouses.view"] },
    ],
  },
  {
    label: "Procurement",
    items: [
      { label: "Suppliers", href: "/suppliers", permission: ["inventory.adjust", "suppliers.view", "suppliers.manage"] },
      { label: "Purchase Orders", href: "/purchase-orders", permission: ["inventory.adjust", "purchase_orders.view", "purchase_orders.manage"] },
    ],
  },
  {
    label: "Fulfillment",
    items: [
      { label: "Dashboard", href: "/fulfillment", permission: "fulfillment.view" },
      { label: "Picking", href: "/fulfillment/picking", permission: "fulfillment.view" },
      { label: "Packing", href: "/fulfillment/packing", permission: "fulfillment.view" },
      { label: "Shipments", href: "/fulfillment/shipments", permission: "fulfillment.view" },
      { label: "Exceptions", href: "/fulfillment/exceptions", permission: "fulfillment.view" },
    ],
  },
  {
    label: "Returns & Refunds",
    items: [
      { label: "Returns", href: "/returns", permission: "returns.view" },
      { label: "Receiving", href: "/returns/receiving", permission: "returns.inspect" },
      { label: "Inspection", href: "/returns/inspection", permission: "returns.inspect" },
      { label: "Refunds", href: "/refunds", permission: "payments.refund" },
    ],
  },
  {
    label: "Marketing",
    items: [
      { label: "Marketing hub", href: "/marketing", permission: "marketing.view" },
      { label: "Automations", href: "/marketing/automations", permission: "marketing.view" },
      { label: "Coupons", href: "/coupons", permission: "campaigns.manage" },
      { label: "Campaigns", href: "/campaigns", permission: "campaigns.manage" },
      { label: "Banners", href: "/banners", permission: "campaigns.manage" },
    ],
  },
  {
    label: "Customers",
    items: [
      { label: "Customers", href: "/customers", permission: ["users.manage", "customers.view"] },
      { label: "Segments", href: "/customers/segments", permission: "customers.segment" },
      { label: "Reviews", href: "/reviews", permission: "reviews.view" },
    ],
  },
  {
    label: "Communications",
    items: [
      { label: "Notifications", href: "/notifications", permission: "notifications.view" },
      { label: "Templates", href: "/notification-templates", permission: "notifications.view" },
    ],
  },
  {
    label: "Subscriptions",
    items: [
      { label: "Subscriptions", href: "/subscriptions", permission: "subscriptions.view" },
      { label: "Plans", href: "/subscription-plans", permission: "subscriptions.view" },
    ],
  },
  {
    label: "Loyalty",
    items: [{ label: "Rewards & ledger", href: "/loyalty", permission: "loyalty.view" }],
  },
  {
    label: "Analytics",
    items: [
      { label: "Overview", href: "/analytics", permission: "reports.view" },
      { label: "Sales", href: "/analytics/sales", permission: "reports.view" },
      { label: "Orders", href: "/analytics/orders", permission: "reports.view" },
      { label: "Payments", href: "/analytics/payments", permission: "reports.view" },
      { label: "Products", href: "/analytics/products", permission: "reports.view" },
      { label: "Customers", href: "/analytics/customers", permission: "reports.view" },
      { label: "Search", href: "/analytics/search", permission: "reports.view" },
      { label: "Reviews", href: "/analytics/reviews", permission: "reports.view" },
      { label: "Inventory", href: "/analytics/inventory", permission: "reports.view" },
      { label: "Attention", href: "/analytics/attention", permission: "reports.view" },
      { label: "Returns", href: "/analytics/returns", permission: "reports.view" },
      { label: "Reports", href: "/reports", permission: "reports.view" },
    ],
  },
  {
    label: "System",
    items: [
      { label: "Users & Roles", href: "/coming-soon?feature=Users", permission: "users.manage" },
      { label: "Settings", href: "/settings", permission: "users.manage" },
      { label: "Audit Logs", href: "/audit-logs", permission: "users.manage" },
    ],
  },
];

export function visibleNav(user: AdminUser | null): NavSection[] {
  return NAV_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) =>
      item.permission ? hasPermission(user, item.permission) : true,
    ),
  })).filter((section) => section.items.length > 0);
}
