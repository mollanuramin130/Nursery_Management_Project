"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchFulfillmentQueue, fetchShipments, type ShipmentListRow } from "@/lib/api/fulfillment";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";

export default function ShipmentsPage() {
  const search = useSearchParams();
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const [status, setStatus] = useState(search.get("status") ?? "");
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);
  const [rows, setRows] = useState<ShipmentListRow[]>([]);
  const [readyOrders, setReadyOrders] = useState(0);
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
      if (search.get("ready") === "1") {
        const ready = await fetchFulfillmentQueue("ready_to_ship", { per_page: 50 });
        setReadyOrders(ready.data.length);
        setRows(
          ready.data.map((r) => ({
            id: 0,
            order_id: r.id,
            order_number: r.order_number,
            customer: r.customer,
            carrier: null,
            tracking_number: null,
            status: r.status,
            created_at: r.created_at,
          })),
        );
      } else {
        const res = await fetchShipments({
          status: status || undefined,
          q: debouncedQ || undefined,
          per_page: 50,
        });
        setRows(res.data);
        setReadyOrders(0);
      }
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, status, debouncedQ, search]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Fulfillment", href: "/fulfillment" },
          { label: "Shipments" },
        ]}
      />
      <PageHeader
        title="Shipments"
        description={
          search.get("ready") === "1"
            ? `Packed orders ready to ship (${readyOrders}). Open an order to create a shipment.`
            : "Carrier tracking is stored server-side. Duplicate ship requests are idempotent."
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tracking or PO" />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="shipped">Shipped</option>
          <option value="out_for_delivery">Out for delivery</option>
          <option value="delivered">Delivered</option>
          <option value="delivery_failed">Delivery failed</option>
        </Select>
        <Button type="button" onClick={() => void load()}>
          Apply
        </Button>
        <Link href="/fulfillment/shipments?ready=1">
          <Button type="button" variant="secondary">
            Ready to ship
          </Button>
        </Link>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No shipments" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Shipment</th>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Carrier</th>
                  <th className="px-3 py-2.5">Tracking</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Created</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr key={`${row.id}-${row.order_id}-${idx}`} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5">{row.id || "—"}</td>
                    <td className="px-3 py-2.5 font-mono text-xs">{row.order_number}</td>
                    <td className="px-3 py-2.5">{row.customer ?? "—"}</td>
                    <td className="px-3 py-2.5">{row.carrier ?? "—"}</td>
                    <td className="px-3 py-2.5 font-mono text-xs">{row.tracking_number ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      <Badge>{row.status.replaceAll("_", " ")}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link
                        className="text-[var(--admin-primary)] hover:underline"
                        href={
                          row.id
                            ? `/fulfillment/shipments/${row.id}`
                            : `/fulfillment/orders/${row.order_id}`
                        }
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
