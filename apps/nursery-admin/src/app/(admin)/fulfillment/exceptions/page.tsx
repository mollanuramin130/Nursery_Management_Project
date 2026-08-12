"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ApiError } from "@/lib/api/client";
import { fetchExceptions, type FulfillmentQueueRow } from "@/lib/api/fulfillment";
import { statusTone } from "@/lib/auth/order-transitions";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

export default function ExceptionsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const [rows, setRows] = useState<FulfillmentQueueRow[]>([]);
  const [pickExc, setPickExc] = useState<FulfillmentQueueRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: fulfillment.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchExceptions({ per_page: 50 });
      setRows(res.data);
      setPickExc((res.meta?.pick_exceptions as FulfillmentQueueRow[] | undefined) ?? []);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  const all = [...rows, ...pickExc];

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Fulfillment", href: "/fulfillment" },
          { label: "Exceptions" },
        ]}
      />
      <PageHeader
        title="Delivery & pick exceptions"
        description="Failed deliveries and open pick discrepancies. Orders are not auto-cancelled."
        actions={
          <Button type="button" variant="secondary" onClick={() => void load()}>
            Refresh
          </Button>
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        all.length === 0 ? (
          <EmptyState title="No open exceptions" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Tracking</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {all.map((row) => (
                  <tr key={`${row.id}-${row.status}`} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">{row.order_number}</td>
                    <td className="px-3 py-2.5">{row.customer ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      <Badge tone={statusTone(row.status)}>{row.status.replaceAll("_", " ")}</Badge>
                    </td>
                    <td className="px-3 py-2.5 font-mono text-xs">{row.tracking_number ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      <Link
                        className="text-[var(--admin-primary)] hover:underline"
                        href={`/fulfillment/orders/${row.id}`}
                      >
                        Resolve
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}
    </div>
  );
}
