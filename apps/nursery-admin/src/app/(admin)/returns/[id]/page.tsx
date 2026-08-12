"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  approveReturn,
  fetchReturn,
  inspectReturn,
  markReturnPickedUp,
  receiveReturn,
  refundReturn,
  rejectReturn,
  scheduleReturnPickup,
  type AdminReturnDetail,
} from "@/lib/api/returns";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function ReturnDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "returns.view");
  const canApprove = hasPermission(user, "returns.approve");
  const canReject = hasPermission(user, "returns.reject");
  const canManage = hasPermission(user, "returns.manage");
  const canInspect = hasPermission(user, "returns.inspect");
  const canRefund = hasPermission(user, "payments.refund");
  const push = useToastStore((s) => s.push);

  const [ret, setRet] = useState<AdminReturnDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [rejectReason, setRejectReason] = useState("");
  const [confirmReject, setConfirmReject] = useState(false);
  const [recv, setRecv] = useState<Record<number, { qty: string; condition: string }>>({});
  const [insp, setInsp] = useState<Record<number, { accepted: string; rejected: string; disposition: string }>>({});
  const [createRefund, setCreateRefund] = useState(true);

  const load = useCallback(async () => {
    if (!canView || !Number.isFinite(id)) {
      setLoading(false);
      setError(canView ? "Invalid return" : "Missing permission: returns.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchReturn(id);
      setRet(res.data);
      const r: Record<number, { qty: string; condition: string }> = {};
      const i: Record<number, { accepted: string; rejected: string; disposition: string }> = {};
      for (const item of res.data.items) {
        r[item.id] = {
          qty: String(item.received_qty ?? item.quantity),
          condition: item.condition ?? "GOOD",
        };
        i[item.id] = {
          accepted: String(item.accepted_qty ?? item.received_qty ?? item.quantity),
          rejected: String(item.rejected_qty ?? 0),
          disposition: item.disposition ?? "SELLABLE",
        };
      }
      setRecv(r);
      setInsp(i);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function run(action: () => Promise<unknown>, ok: string) {
    setBusy(true);
    try {
      await action();
      push(ok, "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Action failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingBlock />;
  if (error || !ret) return <ErrorState message={error ?? "Not found"} onRetry={() => void load()} />;

  const actions = ret.actions ?? {};

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Returns", href: "/returns" },
          { label: `#${ret.id}` },
        ]}
      />
      <PageHeader
        title={`Return #${ret.id}`}
        description={`${ret.order_number ?? ""} · ${ret.user?.name ?? ret.customer?.name ?? ""}`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Badge>{ret.status.replaceAll("_", " ")}</Badge>
            <Link href={`/orders/${ret.order_id}`}>
              <Button type="button" variant="secondary">
                Order
              </Button>
            </Link>
          </div>
        }
      />

      <div className="mb-4 flex flex-wrap gap-2">
        {actions.can_approve && canApprove ? (
          <Button type="button" disabled={busy} onClick={() => void run(() => approveReturn(id), "Approved")}>
            Approve
          </Button>
        ) : null}
        {actions.can_reject && canReject ? (
          <Button type="button" variant="danger" disabled={busy} onClick={() => setConfirmReject(true)}>
            Reject
          </Button>
        ) : null}
        {actions.can_schedule_pickup && canManage ? (
          <Button
            type="button"
            disabled={busy}
            onClick={() => void run(() => scheduleReturnPickup(id), "Pickup scheduled")}
          >
            Schedule pickup
          </Button>
        ) : null}
        {actions.can_mark_picked_up && canManage ? (
          <Button
            type="button"
            disabled={busy}
            onClick={() => void run(() => markReturnPickedUp(id), "Picked up")}
          >
            Mark picked up
          </Button>
        ) : null}
        {actions.can_refund && canRefund ? (
          <Button
            type="button"
            disabled={busy}
            onClick={() =>
              void run(
                () =>
                  refundReturn(id, {
                    amount: ret.suggested_refund_amount,
                    idempotency_key: `return-${id}-refund`,
                  }),
                "Refund recorded",
              )
            }
          >
            Record refund ({formatMoney(ret.suggested_refund_amount ?? 0)})
          </Button>
        ) : null}
      </div>

      <div className="grid gap-4 xl:grid-cols-2">
        <FormSection title="Summary">
          <dl className="grid grid-cols-2 gap-2 text-sm">
            <dt className="text-[var(--admin-muted)]">Customer</dt>
            <dd>
              {ret.user?.name ?? ret.customer?.name}
              <div className="text-xs text-[var(--admin-muted)]">
                {ret.user?.email ?? ret.customer?.email}
              </div>
            </dd>
            <dt className="text-[var(--admin-muted)]">Notes</dt>
            <dd>{ret.notes || "—"}</dd>
            <dt className="text-[var(--admin-muted)]">Suggested refund</dt>
            <dd>{formatMoney(ret.suggested_refund_amount ?? 0)}</dd>
            {ret.rejection_reason ? (
              <>
                <dt className="text-[var(--admin-muted)]">Rejection</dt>
                <dd>{ret.rejection_reason}</dd>
              </>
            ) : null}
          </dl>
        </FormSection>

        <FormSection title="Timeline">
          <ul className="space-y-2 text-sm">
            {(ret.timeline ?? []).length === 0 ? (
              <li className="text-[var(--admin-muted)]">No events</li>
            ) : (
              (ret.timeline ?? []).map((ev, idx) => (
                <li key={`${ev.status}-${idx}`} className="border-b border-[var(--admin-border)]/60 pb-2">
                  <div className="font-medium">{ev.status}</div>
                  <div className="text-xs text-[var(--admin-muted)]">
                    {formatDateTime(ev.at)} {ev.note ? `· ${ev.note}` : ""}
                  </div>
                </li>
              ))
            )}
          </ul>
        </FormSection>
      </div>

      <div className="mt-4">
        <FormSection title="Items">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-2 py-2">Item</th>
                <th className="px-2 py-2">Qty</th>
                <th className="px-2 py-2">Reason</th>
                <th className="px-2 py-2">Received</th>
                <th className="px-2 py-2">Accepted</th>
                <th className="px-2 py-2">Disposition</th>
              </tr>
            </thead>
            <tbody>
              {ret.items.map((item) => (
                <tr key={item.id} className="border-b border-[var(--admin-border)]/60">
                  <td className="px-2 py-2">#{item.order_item_id}</td>
                  <td className="px-2 py-2">{item.quantity}</td>
                  <td className="px-2 py-2">{item.reason ?? "—"}</td>
                  <td className="px-2 py-2">
                    {item.received_qty ?? "—"} {item.condition ? `(${item.condition})` : ""}
                  </td>
                  <td className="px-2 py-2">{item.accepted_qty ?? "—"}</td>
                  <td className="px-2 py-2">{item.disposition ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </FormSection>
      </div>

      {actions.can_receive && canInspect ? (
        <div className="mt-4">
          <FormSection title="Warehouse receive">
            <div className="space-y-3">
              {ret.items.map((item) => (
                <div key={item.id} className="grid gap-2 sm:grid-cols-3">
                  <div className="text-sm self-end">Item #{item.order_item_id} (req {item.quantity})</div>
                  <Input
                    label="Received qty"
                    type="number"
                    min="0"
                    max={item.quantity}
                    value={recv[item.id]?.qty ?? "0"}
                    onChange={(e) =>
                      setRecv((p) => ({
                        ...p,
                        [item.id]: { ...(p[item.id] ?? { condition: "GOOD" }), qty: e.target.value },
                      }))
                    }
                  />
                  <Select
                    label="Condition"
                    value={recv[item.id]?.condition ?? "GOOD"}
                    onChange={(e) =>
                      setRecv((p) => ({
                        ...p,
                        [item.id]: { ...(p[item.id] ?? { qty: "0" }), condition: e.target.value },
                      }))
                    }
                  >
                    <option value="GOOD">Good</option>
                    <option value="DAMAGED">Damaged</option>
                    <option value="OPENED">Opened</option>
                    <option value="UNSELLABLE">Unsellable</option>
                  </Select>
                </div>
              ))}
              <Button
                type="button"
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      receiveReturn(
                        id,
                        ret.items.map((item) => ({
                          return_item_id: item.id,
                          received_qty: Math.floor(Number(recv[item.id]?.qty) || 0),
                          condition: recv[item.id]?.condition ?? "GOOD",
                        })),
                      ),
                    "Received",
                  )
                }
              >
                Confirm receipt
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}

      {actions.can_inspect && canInspect ? (
        <div className="mt-4">
          <FormSection
            title="Inspection & disposition"
            description="SELLABLE restocks sellable inventory. DAMAGED marks unsellable. DISPOSAL does not increase stock."
          >
            <div className="space-y-3">
              {ret.items.map((item) => (
                <div key={item.id} className="grid gap-2 rounded border border-[var(--admin-border)] p-3 sm:grid-cols-4">
                  <div className="text-sm self-center">
                    Item #{item.order_item_id}
                    <div className="text-xs text-[var(--admin-muted)]">
                      Received {item.received_qty ?? item.quantity}
                    </div>
                  </div>
                  <Input
                    label="Accepted"
                    type="number"
                    min="0"
                    value={insp[item.id]?.accepted ?? "0"}
                    onChange={(e) =>
                      setInsp((p) => ({
                        ...p,
                        [item.id]: {
                          ...(p[item.id] ?? { rejected: "0", disposition: "SELLABLE" }),
                          accepted: e.target.value,
                        },
                      }))
                    }
                  />
                  <Input
                    label="Rejected"
                    type="number"
                    min="0"
                    value={insp[item.id]?.rejected ?? "0"}
                    onChange={(e) =>
                      setInsp((p) => ({
                        ...p,
                        [item.id]: {
                          ...(p[item.id] ?? { accepted: "0", disposition: "SELLABLE" }),
                          rejected: e.target.value,
                        },
                      }))
                    }
                  />
                  <Select
                    label="Disposition"
                    value={insp[item.id]?.disposition ?? "SELLABLE"}
                    onChange={(e) =>
                      setInsp((p) => ({
                        ...p,
                        [item.id]: {
                          ...(p[item.id] ?? { accepted: "0", rejected: "0" }),
                          disposition: e.target.value,
                        },
                      }))
                    }
                  >
                    <option value="SELLABLE">Sellable</option>
                    <option value="DAMAGED">Damaged</option>
                    <option value="DISPOSAL">Disposal</option>
                  </Select>
                </div>
              ))}
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={createRefund} onChange={(e) => setCreateRefund(e.target.checked)} />
                Create refund after inspection (local stub outside production)
              </label>
              <Button
                type="button"
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      inspectReturn(id, {
                        items: ret.items.map((item) => ({
                          return_item_id: item.id,
                          accepted_qty: Math.floor(Number(insp[item.id]?.accepted) || 0),
                          rejected_qty: Math.floor(Number(insp[item.id]?.rejected) || 0),
                          disposition: insp[item.id]?.disposition ?? "SELLABLE",
                        })),
                        create_refund: createRefund && canRefund,
                        idempotency_key: `return-${id}-refund`,
                      }),
                    "Inspection completed",
                  )
                }
              >
                Complete inspection
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}

      {(ret.pickup_tracking || ret.pickup || ret.reverse_shipment) && (
        <div className="mt-4">
          <FormSection title="Reverse pickup">
            <p className="text-sm">
              {ret.pickup_tracking?.carrier ||
                ret.pickup?.carrier ||
                (ret.reverse_shipment?.carrier as string) ||
                "—"}{" "}
              ·{" "}
              {ret.pickup_tracking?.tracking_number ||
                ret.pickup?.tracking_number ||
                (ret.reverse_shipment?.tracking_number as string) ||
                "—"}
            </p>
          </FormSection>
        </div>
      )}

      <ConfirmDialog
        open={confirmReject}
        title="Reject return?"
        description="Order status returns to DELIVERED. Customer is notified."
        confirmLabel="Reject"
        loading={busy}
        onCancel={() => setConfirmReject(false)}
        onConfirm={() => {
          if (!rejectReason.trim()) {
            push("Reason required", "error");
            return;
          }
          setConfirmReject(false);
          void run(() => rejectReturn(id, rejectReason.trim()), "Rejected");
        }}
      />
      {confirmReject ? (
        <div className="fixed inset-x-0 bottom-4 z-50 mx-auto max-w-md rounded border border-[var(--admin-border)] bg-white p-3 shadow">
          <TextArea
            label="Rejection reason"
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </div>
      ) : null}
    </div>
  );
}
