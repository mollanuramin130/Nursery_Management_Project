"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { fetchDashboard } from "@/lib/api/dashboard";
import { fetchOrders } from "@/lib/api/orders";
import { fetchInventory } from "@/lib/api/inventory";
import { hasPermission } from "@/lib/auth/permissions";
import { statusTone } from "@/lib/auth/order-transitions";
import { formatMoney, formatDateTime } from "@/lib/format";
import type { AdminOrderListItem, DashboardSummary, InventoryRow } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { ApiError } from "@/lib/api/client";

function Kpi({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 shadow-[var(--admin-shadow)]">
      <div className="text-xs font-medium uppercase tracking-wide text-[var(--admin-muted)]">
        {label}
      </div>
      <div className="mt-2 text-2xl font-semibold text-[var(--admin-ink)]">{value}</div>
      {hint ? <div className="mt-1 text-xs text-[var(--admin-muted)]">{hint}</div> : null}
    </div>
  );
}

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);
  const canDashboard = hasPermission(user, "reports.view");
  const canOrders = hasPermission(user, "orders.view");
  const canInventory = hasPermission(user, "inventory.view");

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [recentOrders, setRecentOrders] = useState<AdminOrderListItem[]>([]);
  const [lowStock, setLowStock] = useState<InventoryRow[]>([]);

  const load = useCallback(async () => {
    if (!canDashboard) {
      setLoading(false);
      setError("You do not have permission to view the dashboard (reports.view).");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const dash = await fetchDashboard();
      setSummary(dash.data);

      if (canOrders) {
        const orders = await fetchOrders({ per_page: 8, page: 1 });
        setRecentOrders(orders.data);
      }

      if (canInventory) {
        const inv = await fetchInventory();
        setLowStock(inv.data.filter((row) => row.is_low_stock).slice(0, 8));
      }
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canDashboard, canOrders, canInventory]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Dashboard" }]} />
      <PageHeader
        title="Dashboard"
        description="Today’s operational overview from live Admin API data."
      />
      <p className="mb-4 text-sm">
        <Link href="/analytics?preset=last_30_days" className="font-semibold text-[var(--admin-primary,#166534)]">
          Open analytics & reports →
        </Link>
      </p>

      {loading ? <LoadingBlock label="Loading dashboard…" /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && summary ? (
        <div className="space-y-6">
          <section>
            <h2 className="mb-3 text-sm font-semibold text-[var(--admin-ink)]">Business overview (today)</h2>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Kpi label="Orders today" value={String(summary.kpis.orders_today)} />
              <Kpi
                label="Revenue today"
                value={formatMoney(summary.kpis.sales_today)}
                hint="Gross order value excl. cancelled / payment_failed"
              />
              <Kpi label="Active products" value={String(summary.kpis.active_products)} />
              <Kpi label="Customers" value={String(summary.kpis.customers)} />
            </div>
          </section>

          <section>
            <h2 className="mb-3 text-sm font-semibold text-[var(--admin-ink)]">Order & payment health</h2>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Kpi label="Pending payment" value={String(summary.kpis.pending_payment)} />
              <Kpi label="Orders to ship" value={String(summary.kpis.orders_to_ship)} />
              <Kpi
                label="Payment failed (orders today)"
                value={String(summary.kpis.payment_failed_orders_today ?? 0)}
              />
              <Kpi
                label="Failed payment rows today"
                value={String(summary.kpis.failed_payments_today ?? 0)}
              />
            </div>
          </section>

          <section>
            <h2 className="mb-3 text-sm font-semibold text-[var(--admin-ink)]">Fulfillment & stock</h2>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Kpi label="Low stock items" value={String(summary.kpis.low_stock_items)} />
              <Kpi
                label="Delivery failed (open)"
                value={String(summary.kpis.delivery_failed_open ?? 0)}
              />
              <Kpi label="Open returns" value={String(summary.kpis.open_returns ?? 0)} />
              <Kpi label="Failed queue jobs" value={String(summary.kpis.failed_jobs ?? 0)} />
            </div>
            {summary.definitions?.sales_today ? (
              <p className="mt-2 text-xs text-[var(--admin-muted)]">
                Metric definitions: {summary.definitions.sales_today}
              </p>
            ) : null}
          </section>

          {summary.sales_last_7_days.length ? (
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Sales last 7 days</h2>
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-2 py-2 font-medium">Day</th>
                      <th className="px-2 py-2 font-medium">Orders</th>
                      <th className="px-2 py-2 font-medium">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {summary.sales_last_7_days.map((row) => (
                      <tr key={row.day} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-2 py-2">{row.day}</td>
                        <td className="px-2 py-2">{row.orders}</td>
                        <td className="px-2 py-2">{formatMoney(row.total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          ) : null}

          <div className="grid gap-4 xl:grid-cols-2">
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-semibold">Recent orders</h2>
                {canOrders ? (
                  <Link href="/orders" className="text-xs text-[var(--admin-primary)] hover:underline">
                    View all
                  </Link>
                ) : null}
              </div>
              {!canOrders ? (
                <p className="text-sm text-[var(--admin-muted)]">
                  Requires orders.view to list recent orders.
                </p>
              ) : recentOrders.length === 0 ? (
                <EmptyState title="No orders yet" />
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-left text-sm">
                    <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
                      <tr>
                        <th className="px-2 py-2 font-medium">Order</th>
                        <th className="px-2 py-2 font-medium">Customer</th>
                        <th className="px-2 py-2 font-medium">Amount</th>
                        <th className="px-2 py-2 font-medium">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentOrders.map((order) => (
                        <tr key={order.id} className="border-b border-[var(--admin-border)]/70">
                          <td className="px-2 py-2">
                            <Link
                              href={`/orders/${order.id}`}
                              className="font-medium text-[var(--admin-primary)] hover:underline"
                            >
                              {order.order_number}
                            </Link>
                            <div className="text-xs text-[var(--admin-muted)]">
                              {formatDateTime(order.placed_at)}
                            </div>
                          </td>
                          <td className="px-2 py-2">
                            <div>{order.customer.name ?? "—"}</div>
                            <div className="text-xs text-[var(--admin-muted)]">
                              {order.customer.email}
                            </div>
                          </td>
                          <td className="px-2 py-2">{formatMoney(order.grand_total)}</td>
                          <td className="px-2 py-2">
                            <Badge tone={statusTone(order.status)}>{order.status}</Badge>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Low stock</h2>
              {!canInventory ? (
                <p className="text-sm text-[var(--admin-muted)]">
                  Low-stock list requires inventory.view. KPI count still comes from the dashboard
                  API.
                </p>
              ) : lowStock.length === 0 ? (
                <EmptyState title="No low-stock items" description="Inventory is above thresholds." />
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-left text-sm">
                    <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
                      <tr>
                        <th className="px-2 py-2 font-medium">Product</th>
                        <th className="px-2 py-2 font-medium">SKU</th>
                        <th className="px-2 py-2 font-medium">Stock</th>
                        <th className="px-2 py-2 font-medium">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {lowStock.map((row) => (
                        <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                          <td className="px-2 py-2">{row.product_name ?? `#${row.product_id}`}</td>
                          <td className="px-2 py-2 font-mono text-xs">{row.sku ?? "—"}</td>
                          <td className="px-2 py-2">{row.sellable}</td>
                          <td className="px-2 py-2">
                            <Badge tone="warning">Low</Badge>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        </div>
      ) : null}
    </div>
  );
}
