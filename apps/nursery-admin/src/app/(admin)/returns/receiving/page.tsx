"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ApiError } from "@/lib/api/client";
import { fetchReturns, type AdminReturnRow } from "@/lib/api/returns";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

const QUEUE = ["APPROVED", "PICKUP_SCHEDULED", "PICKED_UP"];

export default function ReturnReceivingPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "returns.inspect") || hasPermission(user, "returns.view");
  const [rows, setRows] = useState<AdminReturnRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: returns.inspect");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const results = await Promise.all(
        QUEUE.map((status) => fetchReturns({ status, per_page: 50, page: 1 })),
      );
      setRows(results.flatMap((r) => r.data).sort((a, b) => b.id - a.id));
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Returns", href: "/returns" },
          { label: "Receiving" },
        ]}
      />
      <PageHeader
        title="Return receiving"
        description="Warehouse queue for approved / in-transit returns awaiting receipt."
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No returns awaiting receipt" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Return</th>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
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
                    <td className="px-3 py-2.5">
                      <Badge tone="info">{row.status.replaceAll("_", " ")}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link className="text-[var(--admin-primary)] hover:underline" href={`/returns/${row.id}`}>
                        Receive
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}

      <div className="mt-3">
        <Button variant="secondary" onClick={() => void load()}>
          Refresh
        </Button>
      </div>
    </div>
  );
}
