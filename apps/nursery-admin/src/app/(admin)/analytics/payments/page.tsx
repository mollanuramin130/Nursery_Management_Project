"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard, TrendBars } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsPayments } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function PaymentsInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsPayments(range), []);

  return (
    <AnalyticsShell
      title="Payment analytics"
      description="Transactional payment rows only. Success rate = success / attempts. Refund amount from refunds table."
      exportType="payments"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const summary = (data.summary ?? {}) as Record<string, number | null>;
        const byStatus = (data.by_status ?? []) as Array<Record<string, unknown>>;
        const byMethod = (data.by_method ?? []) as Array<Record<string, unknown>>;
        const trend = (data.trend ?? []) as Array<Record<string, unknown>>;

        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <KpiCard label="Attempts" value={summary.attempts ?? 0} />
              <KpiCard label="Success" value={summary.success ?? 0} />
              <KpiCard label="Failed" value={summary.failed ?? 0} />
              <KpiCard label="Pending" value={summary.pending ?? 0} />
              <KpiCard
                label="Success rate"
                value={
                  summary.success_rate == null
                    ? "n/a"
                    : `${(Number(summary.success_rate) * 100).toFixed(1)}%`
                }
              />
              <KpiCard label="Refund amount" value={Number(summary.refund_amount ?? 0)} money />
            </section>

            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">By status</h2>
                <AnalyticsTable
                  columns={[
                    { key: "status", label: "Status" },
                    { key: "count", label: "Count", align: "right" },
                    {
                      key: "amount",
                      label: "Amount",
                      align: "right",
                      render: (row) => formatMoney(Number(row.amount ?? 0)),
                    },
                  ]}
                  rows={byStatus}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">By method</h2>
                <AnalyticsTable
                  columns={[
                    { key: "method", label: "Method" },
                    { key: "count", label: "Count", align: "right" },
                    {
                      key: "amount",
                      label: "Amount",
                      align: "right",
                      render: (row) => formatMoney(Number(row.amount ?? 0)),
                    },
                  ]}
                  rows={byMethod}
                />
              </section>
            </div>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Daily payment attempts</h2>
              <TrendBars points={trend} valueKey="attempts" labelKey="day" />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function PaymentsAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <PaymentsInner />
    </Suspense>
  );
}
