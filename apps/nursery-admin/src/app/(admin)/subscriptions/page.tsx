"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchSubscriptionDashboard,
  fetchSubscriptions,
  type AdminSubscription,
  type SubscriptionDashboard,
} from "@/lib/api/subscriptions";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";

function tone(status: string) {
  if (status === "ACTIVE") return "success" as const;
  if (status === "PAUSED" || status === "PENDING") return "warning" as const;
  if (status === "CANCELLED" || status === "PAYMENT_FAILED") return "danger" as const;
  return "neutral" as const;
}

export default function SubscriptionsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "subscriptions.view");
  const [status, setStatus] = useState("");
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);
  const [rows, setRows] = useState<AdminSubscription[]>([]);
  const [dash, setDash] = useState<SubscriptionDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: subscriptions.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [list, d] = await Promise.all([
        fetchSubscriptions({
          status: status || undefined,
          q: debouncedQ || undefined,
          page,
          per_page: 30,
        }),
        fetchSubscriptionDashboard(),
      ]);
      setRows(list.data);
      setDash(d.data);
      setLastPage((list.meta?.pagination as { last_page?: number } | undefined)?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, status, debouncedQ, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Subscriptions" }]} />
      <PageHeader
        title="Subscriptions"
        description="Pay-per-cycle recurring orders. Automatic card charging is not enabled."
        actions={
          <Link href="/subscription-plans">
            <Button variant="secondary">Manage plans</Button>
          </Link>
        }
      />

      {dash ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
          {(
            [
              ["Active", dash.active],
              ["Paused", dash.paused],
              ["Pending", dash.pending],
              ["Payment failed", dash.payment_failed],
              ["Cancelled", dash.cancelled],
              ["Due soon", dash.due_soon],
            ] as const
          ).map(([label, value]) => (
            <div
              key={label}
              className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3"
            >
              <div className="text-xs uppercase text-[var(--admin-muted)]">{label}</div>
              <div className="text-2xl font-semibold tabular-nums">{value ?? 0}</div>
            </div>
          ))}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="SUB-…, email" />
        <Select
          label="Status"
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value);
          }}
        >
          <option value="">All</option>
          {["ACTIVE", "PAUSED", "PENDING", "PAYMENT_FAILED", "CANCELLED", "COMPLETED"].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
        <Button onClick={() => void load()}>Refresh</Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No subscriptions" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-[var(--admin-surface-2)] text-left text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Subscription</th>
                <th className="px-3 py-2">Customer</th>
                <th className="px-3 py-2">Product</th>
                <th className="px-3 py-2">Freq</th>
                <th className="px-3 py-2">Qty</th>
                <th className="px-3 py-2">Price</th>
                <th className="px-3 py-2">Next cycle</th>
                <th className="px-3 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-[var(--admin-border)]">
                  <td className="px-3 py-2">
                    <Link className="font-medium text-[var(--admin-accent)]" href={`/subscriptions/${r.id}`}>
                      {r.subscription_number}
                    </Link>
                  </td>
                  <td className="px-3 py-2">{r.user?.email ?? r.user?.name ?? "—"}</td>
                  <td className="px-3 py-2">{r.product_name}</td>
                  <td className="px-3 py-2">{r.frequency}</td>
                  <td className="px-3 py-2">{r.quantity}</td>
                  <td className="px-3 py-2">{formatMoney(r.unit_price)}</td>
                  <td className="px-3 py-2">{r.next_billing_at ? formatDateTime(r.next_billing_at) : "—"}</td>
                  <td className="px-3 py-2">
                    <Badge tone={tone(r.status)}>{r.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="flex items-center justify-between border-t border-[var(--admin-border)] px-3 py-2">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Prev
            </Button>
            <span className="text-xs text-[var(--admin-muted)]">
              Page {page} / {lastPage}
            </span>
            <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
