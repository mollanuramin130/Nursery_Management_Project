"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { DateRangeFilter } from "@/components/analytics/DateRangeFilter";
import {
  rangeFromSearchParams,
  rangeToQuery,
  type AnalyticsRangeParams,
} from "@/lib/analytics/range";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { ApiError } from "@/lib/api/client";
import { downloadAnalyticsExport } from "@/lib/api/analytics";

const LINKS = [
  { href: "/analytics", label: "Overview" },
  { href: "/analytics/sales", label: "Sales" },
  { href: "/analytics/orders", label: "Orders" },
  { href: "/analytics/payments", label: "Payments" },
  { href: "/analytics/products", label: "Products" },
  { href: "/analytics/plants", label: "Plants" },
  { href: "/analytics/categories", label: "Categories" },
  { href: "/analytics/customers", label: "Customers" },
  { href: "/analytics/search", label: "Search" },
  { href: "/analytics/reviews", label: "Reviews" },
  { href: "/analytics/inventory", label: "Inventory" },
  { href: "/analytics/attention", label: "Attention" },
  { href: "/analytics/returns", label: "Returns" },
  { href: "/analytics/seasonal", label: "Seasonal" },
  { href: "/analytics/campaigns", label: "Campaigns" },
  { href: "/analytics/cohorts", label: "Cohorts" },
  { href: "/reports", label: "Reports" },
];

export function AnalyticsShell({
  title,
  description,
  children,
  exportType,
  load,
}: {
  title: string;
  description: string;
  children: (args: {
    range: AnalyticsRangeParams;
    data: Record<string, unknown> | null;
    reload: () => void;
  }) => React.ReactNode;
  exportType?: string;
  load: (range: AnalyticsRangeParams) => Promise<{ data: Record<string, unknown> }>;
}) {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "reports.view");
  const canExport = hasPermission(user, "reports.export");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const range = useMemo(() => rangeFromSearchParams(searchParams), [searchParams]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [exporting, setExporting] = useState(false);
  const dataRef = useRef(data);
  dataRef.current = data;

  const setRange = (next: AnalyticsRangeParams) => {
    const q = new URLSearchParams(rangeToQuery(next));
    router.replace(`${pathname}?${q.toString()}`);
  };

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setRefreshing(false);
      setError("Missing permission reports.view");
      return;
    }
    const hadData = dataRef.current != null;
    setError(null);
    if (hadData) setRefreshing(true);
    else setLoading(true);
    try {
      const res = await load(range);
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
      if (!hadData) setData(null);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [canView, load, range]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Analytics", href: "/analytics" },
          { label: title },
        ]}
      />
      <PageHeader title={title} description={description} />

      <nav className="mb-4 flex flex-wrap gap-2">
        {LINKS.map((l) => (
          <Link
            key={l.href}
            href={`${l.href}?${new URLSearchParams(rangeToQuery(range)).toString()}`}
            className={`rounded-full border px-3 py-1 text-xs font-medium ${
              pathname === l.href
                ? "border-[var(--admin-primary,#166534)] bg-[var(--admin-primary-soft,#ecfdf5)] text-[var(--admin-primary,#166534)]"
                : "border-[var(--admin-border)] text-[var(--admin-muted)] hover:bg-white"
            }`}
          >
            {l.label}
          </Link>
        ))}
      </nav>

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <DateRangeFilter value={range} onChange={setRange} />
        {exportType && canExport ? (
          <button
            type="button"
            disabled={exporting}
            className="rounded border border-[var(--admin-border)] bg-white px-3 py-2 text-sm font-medium hover:bg-[var(--admin-surface-muted,#f9fafb)]"
            onClick={async () => {
              setExporting(true);
              try {
                await downloadAnalyticsExport(exportType, range);
              } catch (err) {
                setError(err instanceof Error ? err.message : "Export failed");
              } finally {
                setExporting(false);
              }
            }}
          >
            {exporting ? "Exporting…" : "Export CSV"}
          </button>
        ) : null}
      </div>

      {loading && !data ? <LoadingBlock label="Loading analytics…" /> : null}
      {refreshing && data ? (
        <p className="mb-2 text-xs font-medium text-[var(--admin-muted)]">Updating…</p>
      ) : null}
      {error && !data ? <ErrorState message={error} onRetry={() => void reload()} /> : null}
      {error && data ? (
        <p className="mb-3 text-sm text-[var(--admin-danger)]">
          {error}{" "}
          <button type="button" className="font-semibold underline" onClick={() => void reload()}>
            Retry
          </button>
        </p>
      ) : null}
      {data ? children({ range, data, reload }) : null}
    </div>
  );
}
