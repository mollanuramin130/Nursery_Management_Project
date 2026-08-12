"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ApiError } from "@/lib/api/client";
import {
  cancelSubscription,
  fetchSubscription,
  pauseSubscription,
  resumeSubscription,
  type AdminSubscription,
} from "@/lib/api/subscriptions";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function SubscriptionDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "subscriptions.view");
  const canManage = hasPermission(user, "subscriptions.manage");
  const push = useToastStore((s) => s.push);
  const [row, setRow] = useState<AdminSubscription | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!canView || !id) {
      setLoading(false);
      setError("Missing permission or id");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchSubscription(id);
      setRow(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function act(kind: "pause" | "resume" | "cancel") {
    if (!canManage || !row) return;
    if (kind === "cancel" && !window.confirm("Cancel this subscription? Existing orders are not cancelled.")) {
      return;
    }
    setBusy(true);
    try {
      if (kind === "pause") await pauseSubscription(row.id, "Paused by admin");
      if (kind === "resume") await resumeSubscription(row.id);
      if (kind === "cancel") await cancelSubscription(row.id, "Cancelled by admin");
      push("Updated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Subscriptions", href: "/subscriptions" },
          { label: row?.subscription_number ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={row?.subscription_number ?? "Subscription"}
        description="Cycle history links to normal orders for fulfillment, returns, and refunds."
        actions={
          canManage && row ? (
            <div className="flex flex-wrap gap-2">
              {row.actions?.can_pause ? (
                <Button disabled={busy} onClick={() => void act("pause")}>
                  Pause
                </Button>
              ) : null}
              {row.actions?.can_resume ? (
                <Button disabled={busy} onClick={() => void act("resume")}>
                  Resume
                </Button>
              ) : null}
              {row.actions?.can_cancel ? (
                <Button variant="danger" disabled={busy} onClick={() => void act("cancel")}>
                  Cancel
                </Button>
              ) : null}
            </div>
          ) : null
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {row && !loading ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
            <div className="mb-2 flex items-center gap-2">
              <Badge>{row.status}</Badge>
              <span className="text-xs text-[var(--admin-muted)]">{row.frequency}</span>
            </div>
            <dl className="grid grid-cols-2 gap-2 text-sm">
              <dt className="text-[var(--admin-muted)]">Customer</dt>
              <dd>{row.user?.email ?? row.user?.name}</dd>
              <dt className="text-[var(--admin-muted)]">Product</dt>
              <dd>{row.product_name}</dd>
              <dt className="text-[var(--admin-muted)]">Plan</dt>
              <dd>{row.plan_name}</dd>
              <dt className="text-[var(--admin-muted)]">Qty / price</dt>
              <dd>
                {row.quantity} × {formatMoney(row.unit_price)}
              </dd>
              <dt className="text-[var(--admin-muted)]">Next cycle</dt>
              <dd>{row.next_billing_at ? formatDateTime(row.next_billing_at) : "—"}</dd>
              <dt className="text-[var(--admin-muted)]">Cycles paid</dt>
              <dd>{row.cycle_count}</dd>
            </dl>
            {row.shipping_address ? (
              <p className="mt-3 text-sm text-[var(--admin-muted)]">
                Ship to {row.shipping_address.name}, {row.shipping_address.line1}, {row.shipping_address.city}
              </p>
            ) : null}
          </div>

          <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
            <h3 className="mb-2 font-semibold">Cycles</h3>
            <ul className="space-y-2 text-sm">
              {(row.cycles ?? []).map((c) => (
                <li key={c.id} className="flex justify-between gap-2 border-b border-[var(--admin-border)] py-1">
                  <span>
                    #{c.cycle_number} · {c.status}
                    {c.order_id ? (
                      <>
                        {" · "}
                        <Link className="text-[var(--admin-accent)]" href={`/orders/${c.order_id}`}>
                          {c.order_number ?? `Order ${c.order_id}`}
                        </Link>
                      </>
                    ) : null}
                  </span>
                  <span>{formatMoney(c.amount)}</span>
                </li>
              ))}
              {(row.cycles ?? []).length === 0 ? <li className="text-[var(--admin-muted)]">No cycles yet</li> : null}
            </ul>
          </div>

          <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 lg:col-span-2">
            <h3 className="mb-2 font-semibold">Events</h3>
            <ul className="max-h-64 space-y-1 overflow-auto text-sm">
              {(row.events ?? []).map((e, i) => (
                <li key={`${e.event_type}-${i}`} className="flex justify-between gap-2">
                  <span>{e.event_type}</span>
                  <span className="text-[var(--admin-muted)]">
                    {e.created_at ? formatDateTime(e.created_at) : ""}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      ) : null}
    </div>
  );
}
