"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchFulfillmentQueue, type FulfillmentQueueRow } from "@/lib/api/fulfillment";
import { statusTone } from "@/lib/auth/order-transitions";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";

export default function PickingQueuePage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);
  const [rows, setRows] = useState<FulfillmentQueueRow[]>([]);
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
      const res = await fetchFulfillmentQueue("picking", {
        q: debouncedQ || undefined,
        per_page: 50,
      });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, debouncedQ]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Fulfillment", href: "/fulfillment" },
          { label: "Picking" },
        ]}
      />
      <PageHeader title="Picking" description="CONFIRMED → start pick → PROCESSING. Over-pick is rejected by the API." />

      <div className="mb-4 flex flex-wrap gap-2">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Order or customer" />
        <div className="flex items-end">
          <Button type="button" onClick={() => void load()}>
            Refresh
          </Button>
        </div>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No orders in picking queue" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Items</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Confirmed</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">{row.order_number}</td>
                    <td className="px-3 py-2.5">{row.customer ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      {row.item_count} lines / {row.units} units
                    </td>
                    <td className="px-3 py-2.5">
                      <Badge tone={statusTone(row.status)}>{row.status.replaceAll("_", " ")}</Badge>
                      {row.has_open_exception ? (
                        <Badge tone="warning" className="ml-1">
                          Exception
                        </Badge>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.confirmed_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link
                        className="text-[var(--admin-primary)] hover:underline"
                        href={`/fulfillment/orders/${row.id}`}
                      >
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
    </div>
  );
}
