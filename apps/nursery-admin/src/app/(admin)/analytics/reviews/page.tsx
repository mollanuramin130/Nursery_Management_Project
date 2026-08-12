"use client";

import Link from "next/link";
import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable, KpiCard } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsReviews } from "@/lib/api/analytics";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function ReviewsInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsReviews(range), []);

  return (
    <AnalyticsShell
      title="Review analytics"
      description="Reviews created in range. Low-rated products need attention — reviews are never auto-removed."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        if (data.supported === false) {
          return <p className="text-sm text-[var(--admin-muted)]">{String(data.reason)}</p>;
        }
        const summary = (data.summary ?? {}) as Record<string, unknown>;
        const distribution = (data.rating_distribution ?? []) as Array<Record<string, unknown>>;
        const low = (data.low_rated_products ?? []) as Array<Record<string, unknown>>;

        return (
          <div className="space-y-6">
            <section className="grid gap-3 sm:grid-cols-3">
              <KpiCard label="Reviews" value={Number(summary.reviews ?? 0)} />
              <KpiCard
                label="Average rating"
                value={summary.average_rating == null ? "n/a" : String(summary.average_rating)}
              />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Rating distribution</h2>
                <AnalyticsTable
                  columns={[
                    { key: "rating", label: "Stars" },
                    { key: "count", label: "Reviews", align: "right" },
                  ]}
                  rows={distribution}
                />
              </section>
              <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold">Lower-rated products (≥2 reviews, avg ≤ 3)</h2>
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
                    { key: "avg_rating", label: "Avg", align: "right" },
                    { key: "review_count", label: "Count", align: "right" },
                  ]}
                  rows={low}
                />
              </section>
            </div>
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function ReviewsAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <ReviewsInner />
    </Suspense>
  );
}
