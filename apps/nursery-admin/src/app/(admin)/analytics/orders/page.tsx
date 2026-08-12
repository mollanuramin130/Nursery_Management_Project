"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsOrders } from "@/lib/api/analytics";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function OrdersInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsOrders(range), []);
  return (
    <AnalyticsShell
      title="Order analytics"
      description="Order counts, status distribution, and cancellation trend. Full payment funnel events are not stored separately."
      exportType="orders_status"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const s = (data.summary ?? {}) as Record<string, number>;
        const status = (data.status_distribution ?? []) as Array<Record<string, unknown>>;
        const funnel = (data.funnel ?? {}) as { supported?: boolean; reason?: string };
        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="Total orders" value={s.total_orders ?? 0} />
              <KpiCard label="Qualifying orders" value={s.qualifying_orders ?? 0} hint="Excludes cancelled / payment failed" />
              <KpiCard label="Delivered" value={s.delivered_orders ?? 0} />
              <KpiCard label="Cancelled" value={s.cancelled_orders ?? 0} />
              <KpiCard label="Return/refund path" value={s.returned_or_refund_path_orders ?? 0} />
              <KpiCard label="Open pipeline" value={s.open_pipeline_orders ?? 0} />
              <KpiCard label="AOV" value={s.average_order_value ?? 0} money />
              <KpiCard label="Revenue" value={s.revenue ?? 0} money />
            </section>
            {!funnel.supported ? (
              <p className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Conversion funnel unavailable: {funnel.reason}
              </p>
            ) : null}
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-semibold">Status distribution</h2>
                <Link href="/orders" className="text-xs font-semibold text-[var(--admin-primary,#166534)]">
                  Drill to orders →
                </Link>
              </div>
              <AnalyticsTable
                columns={[
                  { key: "status", label: "Status" },
                  { key: "count", label: "Count", align: "right" },
                ]}
                rows={status}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function OrdersAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <OrdersInner />
    </Suspense>
  );
}
