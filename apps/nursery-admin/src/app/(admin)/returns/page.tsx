"use client";

import Link from "next/link";
import { Suspense, useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchReturns,
  fetchReturnsDashboard,
  type AdminReturnRow,
  type ReturnsDashboard,
} from "@/lib/api/returns";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

function tone(status: string) {
  if (status === "COMPLETED") return "success" as const;
  if (status === "REJECTED" || status === "CANCELLED") return "danger" as const;
  if (status === "RETURN_REQUESTED") return "warning" as const;
  return "info" as const;
}

function ReturnsPageInner() {
  const searchParams = useSearchParams();
  const initialStatus = searchParams.get("status") ?? "";
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "returns.view");
  const [status, setStatus] = useState(initialStatus);
  const [q, setQ] = useState("");
  const [rows, setRows] = useState<AdminReturnRow[]>([]);
  const [dash, setDash] = useState<ReturnsDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    setStatus(initialStatus);
    setPage(1);
  }, [initialStatus]);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: returns.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [list, d] = await Promise.all([
        fetchReturns({ status: status || undefined, q: q || undefined, page, per_page: 30 }),
        fetchReturnsDashboard(),
      ]);
      setRows(list.data);
      setDash(d.data);
      setLastPage((list.meta?.pagination as { last_page?: number } | undefined)?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, status, q, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Returns" }]} />
      <PageHeader
        title="Returns"
        description="Review → approve → pickup/receive → inspect → refund. Inventory restocks only after inspection disposition."
      />

      {dash ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
          {[
            ["Pending review", dash.requested],
            ["Approved", dash.approved],
            ["Pickup", Number(dash.pickup_scheduled ?? 0) + Number(dash.picked_up ?? 0)],
            ["Inspection", Number(dash.received ?? 0) + Number(dash.under_inspection ?? 0)],
            ["Completed", dash.completed],
            ["Rejected", dash.rejected],
          ].map(([label, value]) => (
            <div
              key={String(label)}
              className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3"
            >
              <div className="text-xs uppercase text-[var(--admin-muted)]">{label}</div>
              <div className="text-2xl font-semibold tabular-nums">{value ?? 0}</div>
            </div>
          ))}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Return ID, order, email" />
        <Select label="Status" value={status} onChange={(e) => { setPage(1); setStatus(e.target.value); }}>
          <option value="">All</option>
          <option value="RETURN_REQUESTED">Requested</option>
          <option value="APPROVED">Approved</option>
          <option value="PICKUP_SCHEDULED">Pickup scheduled</option>
          <option value="PICKED_UP">Picked up</option>
          <option value="RECEIVED">Received</option>
          <option value="UNDER_INSPECTION">Under inspection</option>
          <option value="COMPLETED">Completed</option>
          <option value="REJECTED">Rejected</option>
        </Select>
        <Button type="button" onClick={() => void load()}>
          Apply
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No returns" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Return</th>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Items</th>
                  <th className="px-3 py-2.5">Suggested refund</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Requested</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">#{row.id}</td>
                    <td className="px-3 py-2.5 font-mono text-xs">{row.order_number}</td>
                    <td className="px-3 py-2.5">{row.user?.name ?? row.customer?.name ?? "—"}</td>
                    <td className="px-3 py-2.5">{row.items?.length ?? 0}</td>
                    <td className="px-3 py-2.5">{formatMoney(row.suggested_refund_amount ?? 0)}</td>
                    <td className="px-3 py-2.5">
                      <Badge tone={tone(row.status)}>{row.status.replaceAll("_", " ")}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link className="text-[var(--admin-primary)] hover:underline" href={`/returns/${row.id}`}>
                        Open
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}

      {lastPage > 1 ? (
        <div className="mt-3 flex gap-2">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="self-center text-sm text-[var(--admin-muted)]">
            Page {page} / {lastPage}
          </span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export default function ReturnsPage() {
  return (
    <Suspense fallback={<LoadingBlock />}>
      <ReturnsPageInner />
    </Suspense>
  );
}
