"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsReturns } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function ReturnsInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsReturns(range), []);
  return (
    <AnalyticsShell
      title="Returns & refunds"
      description="Return requests and refund amounts from return_requests and refunds tables."
      exportType="returns"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const s = (data.summary ?? {}) as {
          return_requests?: number;
          refund_amount?: number;
          return_by_status?: Record<string, number>;
          refund_by_status?: Array<Record<string, unknown>>;
        };
        const trend = (data.trend ?? []) as Array<Record<string, unknown>>;
        const returnStatusRows = Object.entries(s.return_by_status ?? {}).map(([status, count]) => ({
          status,
          count,
        }));
        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <KpiCard label="Return requests" value={s.return_requests ?? 0} />
              <KpiCard label="Refund amount" value={s.refund_amount ?? 0} money />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Returns by status</h2>
                <AnalyticsTable
                  columns={[
                    { key: "status", label: "Status" },
                    { key: "count", label: "Count", align: "right" },
                  ]}
                  rows={returnStatusRows}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <div className="mb-3 flex justify-between">
                  <h2 className="text-sm font-semibold">Refunds by status</h2>
                  <Link href="/refunds" className="text-xs font-semibold text-[var(--admin-primary,#166534)]">
                    Refunds →
                  </Link>
                </div>
                <AnalyticsTable
                  columns={[
                    { key: "status", label: "Status" },
                    { key: "count", label: "Count", align: "right" },
                    {
                      key: "amount",
                      label: "Amount",
                      align: "right",
                      render: (r) => formatMoney(Number(r.amount ?? 0)),
                    },
                  ]}
                  rows={s.refund_by_status ?? []}
                />
              </section>
            </div>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Returns trend</h2>
              <AnalyticsTable
                columns={[
                  { key: "day", label: "Day" },
                  { key: "returns", label: "Returns", align: "right" },
                ]}
                rows={trend}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function ReturnsAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <ReturnsInner />
    </Suspense>
  );
}
