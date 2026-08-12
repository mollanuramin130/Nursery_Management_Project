"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchOrders } from "@/lib/api/orders";
import { hasPermission } from "@/lib/auth/permissions";
import { ORDER_STATUSES, statusTone } from "@/lib/auth/order-transitions";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { AdminOrderListItem, Pagination } from "@/lib/types";
import { useAuthStore } from "@/store/auth";

export default function OrdersPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "orders.view");

  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [applied, setApplied] = useState({ q: "", status: "" });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [rows, setRows] = useState<AdminOrderListItem[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: orders.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchOrders({
        q: applied.q || undefined,
        status: applied.status || undefined,
        page,
        per_page: 20,
      });
      setRows(res.data);
      setPagination(res.meta?.pagination ?? null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, applied, page]);

  useEffect(() => {
    void load();
  }, [load]);

  function applyFilters() {
    setPage(1);
    setApplied({ q, status });
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Orders" }]} />
      <PageHeader
        title="Orders"
        description="Search and filter orders with server-side pagination."
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input
          label="Search"
          placeholder="Order #, customer name or email"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="min-w-[220px]"
        />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All statuses</option>
          {ORDER_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
        <Button onClick={applyFilters}>Apply</Button>
        <Button variant="secondary" onClick={() => void load()}>
          Refresh
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && rows.length === 0 ? (
        <EmptyState title="No orders found" description="Try clearing filters or search." />
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5 font-medium">Order</th>
                <th className="px-3 py-2.5 font-medium">Customer</th>
                <th className="px-3 py-2.5 font-medium">Amount</th>
                <th className="px-3 py-2.5 font-medium">Payment</th>
                <th className="px-3 py-2.5 font-medium">Status</th>
                <th className="px-3 py-2.5 font-medium">Created</th>
                <th className="px-3 py-2.5 font-medium">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((order) => (
                <tr key={order.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5 font-medium">{order.order_number}</td>
                  <td className="px-3 py-2.5">
                    <div>{order.customer.name ?? "—"}</div>
                    <div className="text-xs text-[var(--admin-muted)]">{order.customer.email}</div>
                  </td>
                  <td className="px-3 py-2.5">{formatMoney(order.grand_total)}</td>
                  <td className="px-3 py-2.5">
                    <div className="text-xs uppercase">{order.payment_method ?? "—"}</div>
                    <div className="text-xs text-[var(--admin-muted)]">
                      {order.payment_status ?? "—"}
                    </div>
                  </td>
                  <td className="px-3 py-2.5">
                    <Badge tone={statusTone(order.status)}>{order.status}</Badge>
                  </td>
                  <td className="px-3 py-2.5 text-xs">{formatDateTime(order.placed_at)}</td>
                  <td className="px-3 py-2.5">
                    <Link
                      href={`/orders/${order.id}`}
                      className="text-[var(--admin-primary)] hover:underline"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {pagination ? (
            <div className="flex items-center justify-between gap-3 border-t border-[var(--admin-border)] px-3 py-2 text-xs text-[var(--admin-muted)]">
              <span>
                Page {pagination.current_page} of {pagination.last_page} · {pagination.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
