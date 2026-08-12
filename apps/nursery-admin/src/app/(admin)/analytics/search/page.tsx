"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsSearch } from "@/lib/api/analytics";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function SearchInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsSearch(range), []);

  return (
    <AnalyticsShell
      title="Search analytics"
      description="Server-side catalog search logs (q length ≥ 2). Not a full conversion funnel."
      exportType="search"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        if (data.supported === false) {
          return <p className="text-sm text-[var(--admin-muted)]">{String(data.reason)}</p>;
        }
        const summary = (data.summary ?? {}) as Record<string, number | null>;
        const top = (data.top_searches ?? []) as Array<Record<string, unknown>>;
        const zero = (data.zero_result_queries ?? []) as Array<Record<string, unknown>>;

        return (
          <div className="space-y-6">
            <p className="text-xs text-[var(--admin-muted)]">{String(data.note ?? "")}</p>
            <section className="grid gap-3 sm:grid-cols-3">
              <KpiCard label="Searches" value={summary.searches ?? 0} />
              <KpiCard label="Zero-result" value={summary.zero_result_searches ?? 0} />
              <KpiCard
                label="Zero-result rate"
                value={
                  summary.zero_result_rate == null
                    ? "n/a"
                    : `${(Number(summary.zero_result_rate) * 100).toFixed(1)}%`
                }
              />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Top searches</h2>
                <AnalyticsTable
                  columns={[
                    { key: "query", label: "Query" },
                    { key: "searches", label: "Count", align: "right" },
                    { key: "avg_results", label: "Avg results", align: "right" },
                    { key: "zero_results", label: "Zero hits", align: "right" },
                  ]}
                  rows={top}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Zero-result queries</h2>
                <AnalyticsTable
                  columns={[
                    { key: "query", label: "Query" },
                    { key: "searches", label: "Count", align: "right" },
                  ]}
                  rows={zero}
                />
              </section>
            </div>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function SearchAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <SearchInner />
    </Suspense>
  );
}
