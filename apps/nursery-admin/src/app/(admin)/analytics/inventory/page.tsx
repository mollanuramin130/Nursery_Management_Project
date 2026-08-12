"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsInventory } from "@/lib/api/analytics";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function InventoryInner() {
  const load = useCallback(async (_range: AnalyticsRangeParams) => fetchAnalyticsInventory(), []);
  return (
    <AnalyticsShell
      title="Inventory analytics"
      description="Stock health uses inventory_items.low_stock_threshold. Date filter does not change current stock snapshot."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const s = (data.summary ?? {}) as Record<string, number>;
        const defs = (data.stock_health_definitions ?? {}) as Record<string, string>;
        const rows = (data.rows ?? []) as Array<Record<string, unknown>>;
        const movements = (data.movements_last_30_days ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="SKU locations" value={s.sku_locations ?? 0} />
              <KpiCard label="Active products" value={s.active_products ?? 0} />
              <KpiCard label="Sellable units" value={s.total_sellable_units ?? 0} />
              <KpiCard label="Healthy" value={s.healthy ?? 0} />
              <KpiCard label="Low stock" value={s.low_stock ?? 0} />
              <KpiCard label="Critical" value={s.critical ?? 0} />
              <KpiCard label="Out of stock" value={s.out_of_stock ?? 0} />
            </section>
            <section className="rounded border border-[var(--admin-border)] bg-white p-3 text-xs text-[var(--admin-muted)]">
              {Object.entries(defs).map(([k, v]) => (
                <div key={k}>
                  <strong className="text-[var(--admin-ink)]">{k}</strong>: {v}
                </div>
              ))}
            </section>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <div className="mb-3 flex justify-between">
                <h2 className="text-sm font-semibold">Stock rows (lowest sellable first)</h2>
                <Link href="/inventory" className="text-xs font-semibold text-[var(--admin-primary,#166534)]">
                  Inventory →
                </Link>
              </div>
              <AnalyticsTable
                columns={[
                  { key: "name", label: "Product" },
                  { key: "sku", label: "SKU" },
                  { key: "sellable", label: "Sellable", align: "right" },
                  { key: "low_stock_threshold", label: "Reorder lvl", align: "right" },
                  { key: "stock_status", label: "Status" },
                ]}
                rows={rows}
              />
            </section>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Movements (last 30 days)</h2>
              <AnalyticsTable
                columns={[
                  { key: "type", label: "Type" },
                  { key: "qty_delta_sum", label: "Qty Δ sum", align: "right" },
                ]}
                rows={movements}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function InventoryAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <InventoryInner />
    </Suspense>
  );
}
