"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard, TrendBars } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsSales } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function SalesInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsSales(range), []);
  return (
    <AnalyticsShell
      title="Sales analytics"
      description="Revenue, discounts, tax, shipping, and payment-method breakdown from order aggregates."
      exportType="sales"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const s = (data.summary ?? {}) as Record<string, number>;
        const c = (data.comparison ?? {}) as Record<string, number | null>;
        const trend = (data.trend ?? {}) as { points?: Array<Record<string, unknown>>; granularity?: string };
        const byPay = (data.by_payment_method ?? []) as Array<Record<string, unknown>>;
        const byCat = (data.by_category ?? []) as Array<Record<string, unknown>>;
        const byProd = (data.by_product ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="Revenue" value={s.revenue ?? 0} money changePct={c.revenue_change_pct} />
              <KpiCard label="Orders" value={s.orders ?? 0} changePct={c.orders_change_pct} />
              <KpiCard label="Units" value={s.units_sold ?? 0} />
              <KpiCard label="AOV" value={s.average_order_value ?? 0} money />
              <KpiCard label="Discounts" value={s.discount_total ?? 0} money />
              <KpiCard label="Shipping" value={s.shipping_total ?? 0} money />
              <KpiCard label="Tax" value={s.tax_total ?? 0} money />
              <KpiCard label="Refunds" value={s.refund_amount ?? 0} money />
            </section>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Trend ({trend.granularity})</h2>
              <TrendBars points={trend.points ?? []} />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">By payment method</h2>
                <AnalyticsTable
                  columns={[
                    { key: "payment_method", label: "Method" },
                    { key: "orders", label: "Orders", align: "right" },
                    {
                      key: "revenue",
                      label: "Revenue",
                      align: "right",
                      render: (r) => formatMoney(Number(r.revenue ?? 0)),
                    },
                  ]}
                  rows={byPay}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">By category</h2>
                <AnalyticsTable
                  columns={[
                    { key: "name", label: "Category" },
                    { key: "orders", label: "Orders", align: "right" },
                    {
                      key: "revenue",
                      label: "Revenue",
                      align: "right",
                      render: (r) => formatMoney(Number(r.revenue ?? 0)),
                    },
                  ]}
                  rows={byCat}
                />
              </section>
            </div>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">By product</h2>
              <AnalyticsTable
                columns={[
                  { key: "name", label: "Product" },
                  { key: "sku", label: "SKU" },
                  { key: "units", label: "Units", align: "right" },
                  {
                    key: "revenue",
                    label: "Revenue",
                    align: "right",
                    render: (r) => formatMoney(Number(r.revenue ?? 0)),
                  },
                ]}
                rows={byProd}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function SalesAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <SalesInner />
    </Suspense>
  );
}
