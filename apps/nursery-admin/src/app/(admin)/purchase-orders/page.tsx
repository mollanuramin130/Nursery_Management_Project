"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchPurchaseOrders, type PurchaseOrderListRow } from "@/lib/api/suppliers";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

function statusTone(status: string) {
  if (status === "received") return "success" as const;
  if (status === "partially_received" || status === "approved") return "warning" as const;
  if (status === "cancelled") return "danger" as const;
  return "neutral" as const;
}

export default function PurchaseOrdersPage() {
  const user = useAuthStore((s) => s.user);
  const canAdjust = hasPermission(user, "inventory.adjust");
  const [status, setStatus] = useState("");
  const [rows, setRows] = useState<PurchaseOrderListRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = useCallback(async () => {
    if (!canAdjust) {
      setLoading(false);
      setError("Missing permission: inventory.adjust");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPurchaseOrders({
        status: status || undefined,
        page,
        per_page: 30,
      });
      setRows(res.data);
      const p = res.meta?.pagination as { last_page?: number } | undefined;
      setLastPage(p?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canAdjust, status, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Purchase Orders" }]} />
      <PageHeader
        title="Purchase Orders"
        description="Supplier → PO → receive → inventory. Partial receiving is supported."
        actions={
          canAdjust ? (
            <Link href="/purchase-orders/new">
              <Button type="button">New purchase order</Button>
            </Link>
          ) : undefined
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Select label="Status" value={status} onChange={(e) => { setPage(1); setStatus(e.target.value); }}>
          <option value="">All</option>
          <option value="draft">Draft</option>
          <option value="ordered">Ordered</option>
          <option value="approved">Approved</option>
          <option value="partially_received">Partially received</option>
          <option value="received">Received</option>
          <option value="cancelled">Cancelled</option>
        </Select>
        <Button variant="secondary" onClick={() => void load()}>
          Refresh
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No purchase orders" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">PO</th>
                  <th className="px-3 py-2.5">Supplier</th>
                  <th className="px-3 py-2.5">Items</th>
                  <th className="px-3 py-2.5">Received</th>
                  <th className="px-3 py-2.5">Total</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Created</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">{row.po_number}</td>
                    <td className="px-3 py-2.5">{row.supplier ?? "—"}</td>
                    <td className="px-3 py-2.5">{row.item_count}</td>
                    <td className="px-3 py-2.5">
                      {row.units_received}/{row.units_ordered}
                    </td>
                    <td className="px-3 py-2.5">{formatMoney(row.grand_total)}</td>
                    <td className="px-3 py-2.5">
                      <Badge tone={statusTone(row.status)}>{row.status.replaceAll("_", " ")}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link className="text-[var(--admin-primary)] hover:underline" href={`/purchase-orders/${row.id}`}>
                        View
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
