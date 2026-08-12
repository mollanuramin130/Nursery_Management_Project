"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchOrders } from "@/lib/api/orders";
import { createRefund, fetchRefunds, type AdminRefund } from "@/lib/api/refunds";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { AdminOrderListItem, Pagination } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function RefundsPage() {
  const user = useAuthStore((s) => s.user);
  const canRefund = hasPermission(user, "payments.refund");
  const push = useToastStore((s) => s.push);

  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<AdminRefund[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [orders, setOrders] = useState<AdminOrderListItem[]>([]);
  const [orderId, setOrderId] = useState("");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [note, setNote] = useState("");
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canRefund) {
      setLoading(false);
      setError("Missing permission: payments.refund");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchRefunds({ page, per_page: 20 });
      setRows(res.data);
      setPagination(res.meta?.pagination ?? null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canRefund, page]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!canRefund) return;
    void (async () => {
      try {
        const res = await fetchOrders({ per_page: 50, page: 1 });
        setOrders(res.data);
      } catch {
        setOrders([]);
      }
    })();
  }, [canRefund]);

  async function onCreate() {
    setSaving(true);
    try {
      await createRefund({
        order_id: Number(orderId),
        amount: Number(amount),
        reason: reason.trim() || undefined,
        note: note.trim() || undefined,
      });
      push("Refund recorded locally (no PSP payout)", "success");
      setConfirmOpen(false);
      setOrderId("");
      setAmount("");
      setReason("");
      setNote("");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Refund failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Refunds" }]} />
      <PageHeader
        title="Refunds"
        description="Create and list refunds against orders."
      />

      <div className="mb-4 rounded-[var(--admin-radius-lg)] border border-[var(--admin-warning)]/40 bg-[var(--admin-warning)]/10 px-3 py-2 text-sm text-[var(--admin-ink)]">
        Refunds are <strong>not live payment-provider payouts</strong>. Non-production records
        them as <code>recorded_local</code> without moving money or marking orders REFUNDED.
        Production refuses stub refunds until gateway integration ships.
      </div>

      {canRefund ? (
        <FormSection title="Create refund" description="Confirm before submit.">
          <form
            className="grid gap-3 md:grid-cols-2"
            onSubmit={(e: FormEvent) => {
              e.preventDefault();
              setConfirmOpen(true);
            }}
          >
            <Select
              label="Order"
              value={orderId}
              onChange={(e) => setOrderId(e.target.value)}
              required
            >
              <option value="">Select order</option>
              {orders.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.order_number} · {formatMoney(o.grand_total)} · {o.status}
                </option>
              ))}
            </Select>
            <Input
              label="Amount"
              type="number"
              min="0"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
            <Input
              label="Reason"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
            />
            <TextArea label="Note" value={note} onChange={(e) => setNote(e.target.value)} />
            <div className="md:col-span-2">
              <Button type="submit" disabled={!orderId || !amount}>
                Create refund…
              </Button>
            </div>
          </form>
        </FormSection>
      ) : null}

      <div className="mt-4">
        {loading ? <LoadingBlock /> : null}
        {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
        {!loading && !error && rows.length === 0 ? <EmptyState title="No refunds" /> : null}

        {!loading && !error && rows.length > 0 ? (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">ID</th>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Amount</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Mode</th>
                  <th className="px-3 py-2.5">Created</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">#{row.id}</td>
                    <td className="px-3 py-2.5">
                      <Link
                        href={`/orders/${row.order_id}`}
                        className="text-[var(--admin-primary)] hover:underline"
                      >
                        {row.order_number ?? `#${row.order_id}`}
                      </Link>
                    </td>
                    <td className="px-3 py-2.5 text-xs">
                      {row.customer?.name ?? "—"}
                      {row.customer?.email ? (
                        <div className="text-[var(--admin-muted)]">{row.customer.email}</div>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5">{formatMoney(row.amount)}</td>
                    <td className="px-3 py-2.5">
                      <Badge tone="neutral">{row.status}</Badge>
                    </td>
                    <td className="px-3 py-2.5 font-mono text-xs">{row.mode ?? "—"}</td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
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

      <ConfirmDialog
        open={confirmOpen}
        title="Confirm refund?"
        description={`Refund ${amount ? formatMoney(Number(amount)) : "—"} for order #${orderId}? This uses local_stub/dev processing.`}
        danger
        loading={saving}
        confirmLabel="Create refund"
        onCancel={() => setConfirmOpen(false)}
        onConfirm={() => void onCreate()}
      />
    </div>
  );
}
