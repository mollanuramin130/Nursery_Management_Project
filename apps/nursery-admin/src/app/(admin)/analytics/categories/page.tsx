"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsCategories } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function CategoriesInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsCategories(range), []);
  return (
    <AnalyticsShell
      title="Category analytics"
      description="Revenue and units by product category for revenue-qualifying orders."
      load={load}
    >
      {({ data }) => {
        const rows = ((data?.rows ?? []) as Array<Record<string, unknown>>);
        return (
          <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
            <AnalyticsTable
              columns={[
                { key: "name", label: "Category" },
                { key: "orders", label: "Orders", align: "right" },
                { key: "units", label: "Units", align: "right" },
                {
                  key: "revenue",
                  label: "Revenue",
                  align: "right",
                  render: (r) => formatMoney(Number(r.revenue ?? 0)),
                },
                {
                  key: "average_order_value",
                  label: "AOV",
                  align: "right",
                  render: (r) => formatMoney(Number(r.average_order_value ?? 0)),
                },
              ]}
              rows={rows}
            />
          </section>
        );
      }}
    </AnalyticsShell>
  );
}

export default function CategoriesAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <CategoriesInner />
    </Suspense>
  );
}
