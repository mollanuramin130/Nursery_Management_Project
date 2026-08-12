"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  approvePurchaseOrder,
  cancelPurchaseOrder,
  fetchPurchaseOrder,
  receivePurchaseOrder,
  type PurchaseOrderDetail,
} from "@/lib/api/suppliers";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function PurchaseOrderDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);

  const [po, setPo] = useState<PurchaseOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [confirmCancel, setConfirmCancel] = useState(false);
  const [confirmReceive, setConfirmReceive] = useState(false);
  const [recvQty, setRecvQty] = useState<Record<number, string>>({});
  const [recvDamaged, setRecvDamaged] = useState<Record<number, string>>({});

  const load = useCallback(async () => {
    if (!canAdjust || !Number.isFinite(id)) {
      setLoading(false);
      setError(canAdjust ? "Invalid purchase order" : "Missing permission: inventory.adjust");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPurchaseOrder(id);
      setPo(res.data);
      const next: Record<number, string> = {};
      const dmg: Record<number, string> = {};
      for (const item of res.data.items) {
        next[item.id] = String(item.quantity_remaining);
        dmg[item.id] = "0";
      }
      setRecvQty(next);
      setRecvDamaged(dmg);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canAdjust, id]);

  useEffect(() => {
    void load();
  }, [load]);

  const receiveSummary = useMemo(() => {
    if (!po) return [];
    return po.items
      .map((item) => {
        const qty = Math.max(0, Math.floor(Number(recvQty[item.id]) || 0));
        const damaged = Math.max(0, Math.floor(Number(recvDamaged[item.id]) || 0));
        return { item, qty, damaged, accepted: Math.max(0, qty - damaged) };
      })
      .filter((row) => row.qty > 0);
  }, [po, recvQty, recvDamaged]);

  async function onApprove() {
    setBusy(true);
    try {
      const res = await approvePurchaseOrder(id);
      setPo(res.data);
      push("Purchase order approved", "success");
    } catch (err) {
      push(err instanceof Error ? err.message : "Approve failed", "error");
    } finally {
      setBusy(false);
    }
  }

  async function onCancel() {
    setBusy(true);
    try {
      const res = await cancelPurchaseOrder(id);
      setPo(res.data);
      setConfirmCancel(false);
      push("Purchase order cancelled", "success");
    } catch (err) {
      push(err instanceof Error ? err.message : "Cancel failed", "error");
    } finally {
      setBusy(false);
    }
  }

  async function onReceive() {
    setBusy(true);
    try {
      const items = receiveSummary.map((row) => ({
        purchase_order_item_id: row.item.id,
        quantity: row.qty,
        damaged: row.damaged,
      }));
      if (!items.length) throw new Error("Enter at least one receiving quantity");
      const res = await receivePurchaseOrder(id, { items });
      setPo(res.data);
      setConfirmReceive(false);
      push("Receipt recorded — inventory updated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Receive failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingBlock />;
  if (error || !po) return <ErrorState message={error ?? "Not found"} onRetry={() => void load()} />;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Purchase Orders", href: "/purchase-orders" },
          { label: po.po_number },
        ]}
      />
      <PageHeader
        title={po.po_number}
        description={`${po.supplier ?? "Supplier"} · ${po.status.replaceAll("_", " ")}`}
        actions={
          <div className="flex flex-wrap gap-2">
            {po.actions.can_approve ? (
              <Button type="button" disabled={busy} onClick={() => void onApprove()}>
                Approve
              </Button>
            ) : null}
            {po.actions.can_cancel ? (
              <Button type="button" variant="danger" disabled={busy} onClick={() => setConfirmCancel(true)}>
                Cancel PO
              </Button>
            ) : null}
            <Link href="/purchase-orders">
              <Button type="button" variant="secondary">
                Back
              </Button>
            </Link>
          </div>
        }
      />

      <div className="mb-4 grid gap-3 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 text-sm sm:grid-cols-4">
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Status</div>
          <Badge>{po.status.replaceAll("_", " ")}</Badge>
        </div>
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Created</div>
          {formatDateTime(po.created_at)}
        </div>
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Expected</div>
          {formatDateTime(po.expected_at)}
        </div>
        <div>
          <div className="text-xs uppercase text-[var(--admin-muted)]">Totals</div>
          {formatMoney(po.grand_total)}
        </div>
      </div>

      <FormSection title="Line items">
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-2 py-2">Product</th>
                <th className="px-2 py-2">Ordered</th>
                <th className="px-2 py-2">Received</th>
                <th className="px-2 py-2">Remaining</th>
                <th className="px-2 py-2">Unit cost</th>
                <th className="px-2 py-2">Line total</th>
              </tr>
            </thead>
            <tbody>
              {po.items.map((item) => (
                <tr key={item.id} className="border-b border-[var(--admin-border)]/60">
                  <td className="px-2 py-2">
                    <div className="font-medium">{item.product_name}</div>
                    <div className="font-mono text-xs text-[var(--admin-muted)]">{item.sku}</div>
                  </td>
                  <td className="px-2 py-2">{item.quantity_ordered}</td>
                  <td className="px-2 py-2">{item.quantity_received}</td>
                  <td className="px-2 py-2">{item.quantity_remaining}</td>
                  <td className="px-2 py-2">{formatMoney(item.unit_cost)}</td>
                  <td className="px-2 py-2">{formatMoney(item.line_total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </FormSection>

      {po.actions.can_receive ? (
        <div className="mt-4">
          <FormSection
            title="Goods receiving"
            description="Inventory increases only for accepted units. Damaged units are marked unsellable."
          >
            <div className="space-y-3">
              {po.items
                .filter((item) => item.quantity_remaining > 0)
                .map((item) => (
                  <div
                    key={item.id}
                    className="grid gap-2 rounded border border-[var(--admin-border)] p-3 sm:grid-cols-[1.5fr_1fr_1fr]"
                  >
                    <div>
                      <div className="font-medium">{item.product_name}</div>
                      <div className="text-xs text-[var(--admin-muted)]">
                        Ordered {item.quantity_ordered} · Received {item.quantity_received} · Remaining{" "}
                        {item.quantity_remaining}
                      </div>
                    </div>
                    <Input
                      label="Receive now"
                      type="number"
                      min="0"
                      max={item.quantity_remaining}
                      value={recvQty[item.id] ?? "0"}
                      onChange={(e) => setRecvQty((prev) => ({ ...prev, [item.id]: e.target.value }))}
                    />
                    <Input
                      label="Of which damaged"
                      type="number"
                      min="0"
                      value={recvDamaged[item.id] ?? "0"}
                      onChange={(e) => setRecvDamaged((prev) => ({ ...prev, [item.id]: e.target.value }))}
                    />
                  </div>
                ))}
              <Button type="button" disabled={busy || receiveSummary.length === 0} onClick={() => setConfirmReceive(true)}>
                Confirm receipt
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}

      <ConfirmDialog
        open={confirmCancel}
        title="Cancel purchase order?"
        description="Cancelled POs cannot be received."
        confirmLabel="Cancel PO"
        loading={busy}
        onCancel={() => setConfirmCancel(false)}
        onConfirm={() => void onCancel()}
      />

      <ConfirmDialog
        open={confirmReceive}
        title="Confirm goods receipt"
        description={
          receiveSummary
            .map(
              (r) =>
                `${r.item.product_name}: receive ${r.qty} (accepted ${r.accepted}, damaged ${r.damaged})`,
            )
            .join(" · ") || "No lines"
        }
        confirmLabel="Post receipt"
        loading={busy}
        onCancel={() => setConfirmReceive(false)}
        onConfirm={() => void onReceive()}
      />
    </div>
  );
}
