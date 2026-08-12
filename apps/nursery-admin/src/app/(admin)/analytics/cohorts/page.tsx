"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsCohorts } from "@/lib/api/analytics";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function CohortsInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsCohorts(range), []);

  return (
    <AnalyticsShell
      title="Acquisition cohorts"
      description="Customers by month of first revenue-qualifying order (within range). Basic — not a full retention matrix."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const rows = (data.rows ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-4">
            <p className="text-xs text-[var(--admin-muted)]">{String(data.note ?? "")}</p>
            <AnalyticsTable
              columns={[
                { key: "cohort_month", label: "Cohort month" },
                { key: "customers", label: "Customers", align: "right" },
              ]}
              rows={rows}
            />
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function CohortsAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <CohortsInner />
    </Suspense>
  );
}
