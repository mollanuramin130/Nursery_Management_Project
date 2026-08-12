"use client";

import Link from "next/link";
import { Suspense, useCallback, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader, ErrorState, LoadingBlock } from "@/components/feedback/States";
import { DateRangeFilter } from "@/components/analytics/DateRangeFilter";
import {
  rangeFromSearchParams,
  rangeToQuery,
  type AnalyticsRangeParams,
} from "@/lib/analytics/range";
import { downloadAnalyticsExport, fetchAnalyticsOverview } from "@/lib/api/analytics";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { formatMoney } from "@/lib/format";

const REPORTS = [
  { type: "sales", label: "Sales trend CSV", href: "/analytics/sales" },
  { type: "products", label: "Top products CSV", href: "/analytics/products" },
  { type: "orders_status", label: "Order status CSV", href: "/analytics/orders" },
  { type: "returns", label: "Returns trend CSV", href: "/analytics/returns" },
  { type: "coupons", label: "Coupons CSV", href: "/analytics/campaigns" },
];

function ReportsInner() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "reports.view");
  const canExport = hasPermission(user, "reports.export");
  const router = useRouter();
  const searchParams = useSearchParams();
  const [range, setRangeState] = useState<AnalyticsRangeParams>(() =>
    rangeFromSearchParams(searchParams),
  );
  const [summary, setSummary] = useState<Record<string, number> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);

  const setRange = (next: AnalyticsRangeParams) => {
    setRangeState(next);
    router.replace(`/reports?${new URLSearchParams(rangeToQuery(next)).toString()}`);
  };

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing reports.view");
      return;
    }
    setLoading(true);
    try {
      const res = await fetchAnalyticsOverview(range);
      setSummary((res.data.summary as Record<string, number>) ?? null);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, range]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Reports" }]} />
      <PageHeader
        title="Operational reports"
        description="Reusable analytics exports. Synchronous CSV only; narrow the date range if the dataset is large."
      />
      <DateRangeFilter value={range} onChange={setRange} />
      {loading ? <LoadingBlock label="Loading…" /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && summary ? (
        <div className="mt-4 grid gap-3 sm:grid-cols-3">
          <div className="rounded border border-[var(--admin-border)] bg-white p-3 text-sm">
            Revenue: <strong>{formatMoney(summary.revenue ?? 0)}</strong>
          </div>
          <div className="rounded border border-[var(--admin-border)] bg-white p-3 text-sm">
            Orders: <strong>{summary.orders ?? 0}</strong>
          </div>
          <div className="rounded border border-[var(--admin-border)] bg-white p-3 text-sm">
            AOV: <strong>{formatMoney(summary.average_order_value ?? 0)}</strong>
          </div>
        </div>
      ) : null}
      <ul className="mt-6 space-y-2">
        {REPORTS.map((r) => (
          <li
            key={r.type}
            className="flex flex-wrap items-center justify-between gap-2 rounded border border-[var(--admin-border)] bg-white px-4 py-3"
          >
            <div>
              <p className="font-medium">{r.label}</p>
              <Link
                href={`${r.href}?${new URLSearchParams(rangeToQuery(range))}`}
                className="text-xs text-[var(--admin-primary,#166534)]"
              >
                Open analytics view
              </Link>
            </div>
            {canExport ? (
              <button
                type="button"
                className="rounded border border-[var(--admin-border)] px-3 py-1.5 text-sm"
                disabled={busy === r.type}
                onClick={async () => {
                  setBusy(r.type);
                  try {
                    await downloadAnalyticsExport(r.type, range);
                  } catch (e) {
                    setError(e instanceof Error ? e.message : "Export failed");
                  } finally {
                    setBusy(null);
                  }
                }}
              >
                {busy === r.type ? "…" : "Download CSV"}
              </button>
            ) : (
              <span className="text-xs text-[var(--admin-muted)]">Requires reports.export</span>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default function ReportsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <ReportsInner />
    </Suspense>
  );
}
