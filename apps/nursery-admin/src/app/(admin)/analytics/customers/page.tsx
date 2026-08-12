"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsCustomers } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function CustomersInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsCustomers(range), []);
  return (
    <AnalyticsShell
      title="Customer analytics"
      description="Customer counts and simple segments. No passwords, tokens, or payment credentials are exposed."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const s = (data.summary ?? {}) as Record<string, number>;
        const segments = (data.segments ?? {}) as {
          definitions?: Record<string, string>;
          high_value_in_period?: Array<Record<string, unknown>>;
        };
        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="Total customers" value={s.total_customers ?? 0} />
              <KpiCard label="New customers" value={s.new_customers ?? 0} />
              <KpiCard label="Buyers in period" value={s.buyers_in_period ?? 0} />
              <KpiCard label="Returning buyers (period)" value={s.returning_buyers_in_period ?? 0} />
              <KpiCard label="Lifetime returning" value={s.lifetime_returning_customers ?? 0} />
              <KpiCard label="Orders / buyer" value={s.orders_per_buyer ?? 0} />
              <KpiCard label="Avg buyer spend" value={s.average_buyer_spend ?? 0} money />
            </section>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 text-sm text-[var(--admin-muted)]">
              <h2 className="mb-2 text-sm font-semibold text-[var(--admin-ink)]">Segment definitions</h2>
              <ul className="list-disc space-y-1 pl-5">
                {Object.entries(segments.definitions ?? {}).map(([k, v]) => (
                  <li key={k}>
                    <span className="font-medium text-[var(--admin-ink)]">{k}</span>: {v}
                  </li>
                ))}
              </ul>
            </section>
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">High value in period</h2>
              <AnalyticsTable
                columns={[
                  {
                    key: "user_id",
                    label: "Customer",
                    render: (r) => (
                      <Link href={`/customers/${r.user_id}`} className="text-[var(--admin-primary,#166534)]">
                        #{String(r.user_id)}
                      </Link>
                    ),
                  },
                  { key: "orders", label: "Orders", align: "right" },
                  {
                    key: "spent",
                    label: "Spent",
                    align: "right",
                    render: (r) => formatMoney(Number(r.spent ?? 0)),
                  },
                ]}
                rows={segments.high_value_in_period ?? []}
              />
            </section>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function CustomersAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <CustomersInner />
    </Suspense>
  );
}
