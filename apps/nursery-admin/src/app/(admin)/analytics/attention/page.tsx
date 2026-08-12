"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsAttention } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function AttentionInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsAttention(range), []);

  return (
    <AnalyticsShell
      title="Inventory attention"
      description="High demand (units in range) + live low/out stock. Advisory only — does not create purchase orders."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const rows = (data.high_demand_low_stock ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-4">
            <p className="text-xs text-[var(--admin-muted)]">
              {String((data.definitions as Record<string, string> | undefined)?.high_demand_low_stock ?? "")}
            </p>
            <AnalyticsTable
              columns={[
                {
                  key: "name",
                  label: "Product",
                  render: (row) =>
                    row.product_id ? (
                      <Link
                        className="font-medium text-[var(--admin-primary,#166534)]"
                        href={`/products/${row.product_id}/edit`}
                      >
                        {String(row.name)}
                      </Link>
                    ) : (
                      String(row.name)
                    ),
                },
                { key: "units_sold", label: "Units sold", align: "right" },
                {
                  key: "revenue",
                  label: "Revenue",
                  align: "right",
                  render: (row) => formatMoney(Number(row.revenue ?? 0)),
                },
                { key: "sellable", label: "Sellable now", align: "right" },
                { key: "low_stock_threshold", label: "Threshold", align: "right" },
                { key: "signal", label: "Signal" },
              ]}
              rows={rows}
            />
            <Link href="/inventory" className="text-xs font-semibold text-[var(--admin-primary,#166534)]">
              Open inventory →
            </Link>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function AttentionAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <AttentionInner />
    </Suspense>
  );
}
