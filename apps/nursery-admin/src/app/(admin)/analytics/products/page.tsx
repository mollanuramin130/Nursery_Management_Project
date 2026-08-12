"use client";

import Link from "next/link";
import { Suspense, useCallback, useState } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsProducts } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function ProductsInner() {
  const [sort, setSort] = useState("revenue");
  const load = useCallback(
    (range: AnalyticsRangeParams) => fetchAnalyticsProducts(range, sort),
    [sort],
  );
  return (
    <AnalyticsShell
      title="Product analytics"
      description="Top sellers, revenue leaders, and current stock health. Profit/margin is unavailable without product cost data."
      exportType="products"
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const top = (data.top ?? []) as Array<Record<string, unknown>>;
        const low = (data.low_stock ?? []) as Array<Record<string, unknown>>;
        const out = (data.out_of_stock ?? []) as Array<Record<string, unknown>>;
        return (
          <div className="space-y-6">
            <label className="text-xs font-medium text-[var(--admin-muted)]">
              Sort by
              <select
                className="ml-2 rounded border border-[var(--admin-border)] px-2 py-1 text-sm"
                value={sort}
                onChange={(e) => setSort(e.target.value)}
              >
                <option value="revenue">Revenue</option>
                <option value="units">Units</option>
                <option value="orders">Orders</option>
              </select>
            </label>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Performance</h2>
              <AnalyticsTable
                columns={[
                  {
                    key: "name",
                    label: "Product",
                    render: (r) =>
                      r.product_id ? (
                        <Link href={`/products/${r.product_id}/edit`} className="font-medium text-[var(--admin-primary,#166534)]">
                          {String(r.name)}
                        </Link>
                      ) : (
                        String(r.name)
                      ),
                  },
                  { key: "sku", label: "SKU" },
                  { key: "units", label: "Units", align: "right" },
                  { key: "orders", label: "Orders", align: "right" },
                  {
                    key: "revenue",
                    label: "Revenue",
                    align: "right",
                    render: (r) => formatMoney(Number(r.revenue ?? 0)),
                  },
                  { key: "stock", label: "Stock", align: "right" },
                ]}
                rows={top}
              />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Low stock</h2>
                <AnalyticsTable
                  columns={[
                    { key: "name", label: "Product" },
                    { key: "sellable", label: "Sellable", align: "right" },
                    { key: "threshold", label: "Threshold", align: "right" },
                  ]}
                  rows={low}
                />
                <Link href="/inventory" className="mt-2 inline-block text-xs font-semibold text-[var(--admin-primary,#166534)]">
                  Inventory →
                </Link>
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Out of stock</h2>
                <AnalyticsTable
                  columns={[
                    { key: "name", label: "Product" },
                    { key: "sku", label: "SKU" },
                    { key: "sellable", label: "Sellable", align: "right" },
                  ]}
                  rows={out}
                />
              </section>
            </div>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function ProductsAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <ProductsInner />
    </Suspense>
  );
}
