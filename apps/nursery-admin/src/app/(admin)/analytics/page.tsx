"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard, TrendBars } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsOverview } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function OverviewInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsOverview(range), []);

  return (
    <AnalyticsShell
      title="Executive overview"
      description="Authoritative KPIs from Admin analytics APIs. Revenue excludes cancelled and payment-failed orders."
      exportType="sales"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const summary = (data.summary ?? {}) as Record<string, number>;
        const comparison = (data.comparison ?? {}) as Record<string, number | null>;
        const trend = (data.trend ?? {}) as { granularity?: string; points?: Array<Record<string, unknown>> };
        const status = (data.order_status_distribution ?? []) as Array<Record<string, unknown>>;
        const products = (data.top_products ?? []) as Array<Record<string, unknown>>;
        const categories = (data.top_categories ?? []) as Array<Record<string, unknown>>;
        const ops = (data.operational ?? {}) as Record<string, number>;
        const payments = (data.payment_health ?? {}) as Record<string, number | null>;
        const attention = (data.attention ?? []) as Array<Record<string, unknown>>;
        const gaps = (data.data_gaps ?? []) as Array<Record<string, string>>;
        const rangeMeta = (data.range ?? {}) as Record<string, string>;

        return (
          <div className="space-y-6">
            <p className="text-xs text-[var(--admin-muted)]">
              Range {rangeMeta.from} → {rangeMeta.to} ({rangeMeta.timezone}) · inclusive calendar days ·
              Revenue = gross order totals excl. CANCELLED / PAYMENT_FAILED (not profit, not refund-adjusted)
            </p>

            <section>
              <h2 className="mb-2 text-sm font-semibold">Business overview</h2>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="Revenue" value={summary.revenue ?? 0} money changePct={comparison.revenue_change_pct} />
              <KpiCard label="Orders" value={summary.orders ?? 0} changePct={comparison.orders_change_pct} />
              <KpiCard label="AOV" value={summary.average_order_value ?? 0} money changePct={comparison.aov_change_pct} />
              <KpiCard label="Units sold" value={summary.units_sold ?? 0} changePct={comparison.units_change_pct} />
              <KpiCard label="Delivered" value={summary.delivered_orders ?? 0} />
              <KpiCard label="Cancelled" value={summary.cancelled_orders ?? 0} />
              <KpiCard label="Refund amount" value={summary.refund_amount ?? 0} money />
              <KpiCard label="Discount given" value={summary.discount_total ?? 0} money />
              </div>
            </section>

            <section>
              <h2 className="mb-2 text-sm font-semibold">Payment health (range)</h2>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard label="Payment attempts" value={payments.attempts ?? 0} />
                <KpiCard label="Payment success" value={payments.success ?? 0} />
                <KpiCard label="Payment failed" value={payments.failed ?? 0} />
                <KpiCard label="Payment pending" value={payments.pending ?? 0} />
                <KpiCard
                  label="Payment success rate"
                  value={
                    payments.success_rate == null
                      ? "n/a"
                      : `${(Number(payments.success_rate) * 100).toFixed(1)}%`
                  }
                />
              </div>
            </section>

            <section>
              <h2 className="mb-2 text-sm font-semibold">Order / stock ops (live snapshot)</h2>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <KpiCard label="Pending payment" value={ops.pending_payment ?? 0} hint="Live ops (not range-bound)" />
              <KpiCard label="To ship" value={ops.orders_to_ship ?? 0} hint="Live ops" />
              <KpiCard label="Out for delivery" value={ops.out_for_delivery ?? 0} hint="Live ops" />
              <KpiCard label="Return requested" value={ops.return_requested ?? 0} hint="Live ops" />
              <KpiCard label="Low stock SKUs" value={ops.low_stock_items ?? 0} hint="Live ops" />
              </div>
            </section>

            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">
                  Revenue trend ({trend.granularity ?? "day"})
                </h2>
                <TrendBars points={trend.points ?? []} />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Order status distribution</h2>
                <AnalyticsTable
                  columns={[
                    { key: "status", label: "Status" },
                    { key: "count", label: "Orders", align: "right" },
                  ]}
                  rows={status}
                />
                <Link href="/orders" className="mt-3 inline-block text-xs font-semibold text-[var(--admin-primary,#166534)]">
                  Open orders →
                </Link>
              </section>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Top products by revenue</h2>
                <AnalyticsTable
                  columns={[
                    {
                      key: "name",
                      label: "Product",
                      render: (row) =>
                        row.product_id ? (
                          <Link className="font-medium text-[var(--admin-primary,#166534)]" href={`/products/${row.product_id}/edit`}>
                            {String(row.name)}
                          </Link>
                        ) : (
                          String(row.name)
                        ),
                    },
                    { key: "units", label: "Units", align: "right" },
                    {
                      key: "revenue",
                      label: "Revenue",
                      align: "right",
                      render: (row) => formatMoney(Number(row.revenue ?? 0)),
                    },
                    { key: "stock", label: "Stock", align: "right" },
                  ]}
                  rows={products}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Top categories</h2>
                <AnalyticsTable
                  columns={[
                    { key: "name", label: "Category" },
                    { key: "orders", label: "Orders", align: "right" },
                    { key: "units", label: "Units", align: "right" },
                    {
                      key: "revenue",
                      label: "Revenue",
                      align: "right",
                      render: (row) => formatMoney(Number(row.revenue ?? 0)),
                    },
                  ]}
                  rows={categories}
                />
              </section>
            </div>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-semibold">High demand + low stock (live inventory)</h2>
                <Link href="/analytics/attention" className="text-xs font-semibold text-[var(--admin-primary,#166534)]">
                  Full list →
                </Link>
              </div>
              <AnalyticsTable
                columns={[
                  { key: "name", label: "Product" },
                  { key: "units_sold", label: "Units", align: "right" },
                  { key: "sellable", label: "Sellable", align: "right" },
                  { key: "signal", label: "Signal" },
                ]}
                rows={attention}
              />
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Analytics data gaps</h2>
              <AnalyticsTable
                columns={[
                  { key: "key", label: "Capability" },
                  { key: "status", label: "Status" },
                  { key: "detail", label: "Detail" },
                ]}
                rows={gaps}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function AnalyticsOverviewPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-[var(--admin-muted)]">Loading…</p>}>
      <OverviewInner />
    </Suspense>
  );
}
