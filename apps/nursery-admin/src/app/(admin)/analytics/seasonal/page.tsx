"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsSeasonal } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function SeasonalInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsSeasonal(range), []);
  return (
    <AnalyticsShell
      title="Seasonal demand"
      description="Commercial seasons are not configured. Monthly revenue demand is shown from order dates."
      load={load}
    >
      {({ data }) => {
        const note = String(data?.note ?? "");
        const rows = (data?.by_month ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-4">
            <p className="rounded border border-[var(--admin-border)] bg-white px-3 py-2 text-sm text-[var(--admin-muted)]">
              {note}
            </p>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <AnalyticsTable
                columns={[
                  { key: "month", label: "Month" },
                  { key: "orders", label: "Orders", align: "right" },
                  {
                    key: "revenue",
                    label: "Revenue",
                    align: "right",
                    render: (r) => formatMoney(Number(r.revenue ?? 0)),
                  },
                ]}
                rows={rows}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function SeasonalAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <SeasonalInner />
    </Suspense>
  );
}
