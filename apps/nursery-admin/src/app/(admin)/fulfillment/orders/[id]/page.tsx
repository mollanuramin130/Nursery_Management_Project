"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
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
  completePicking,
  failDelivery,
  fetchFulfillmentOrder,
  markDelivered,
  markOutForDelivery,
  packOrder,
  recordPickException,
  resolveException,
  retryDelivery,
  shipOrder,
  startPicking,
  updatePick,
  type FulfillmentOrder,
} from "@/lib/api/fulfillment";
import { statusTone } from "@/lib/auth/order-transitions";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function FulfillmentOrderPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const canPick = hasPermission(user, "fulfillment.pick");
  const canPack = hasPermission(user, "fulfillment.pack");
  const canShip = hasPermission(user, "fulfillment.ship");
  const canManage = hasPermission(user, "fulfillment.manage");
  const push = useToastStore((s) => s.push);

  const [order, setOrder] = useState<FulfillmentOrder | null>(null);
  const [picked, setPicked] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [packageCount, setPackageCount] = useState("1");
  const [weight, setWeight] = useState("");
  const [carrier, setCarrier] = useState("");
  const [tracking, setTracking] = useState("");
  const [eta, setEta] = useState("");
  const [failReason, setFailReason] = useState("CUSTOMER_UNAVAILABLE");
  const [failNote, setFailNote] = useState("");
  const [confirmShip, setConfirmShip] = useState(false);
  const [excItem, setExcItem] = useState<number | null>(null);
  const [excActual, setExcActual] = useState("");
  const [excNote, setExcNote] = useState("");

  const load = useCallback(async () => {
    if (!canView || !Number.isFinite(id)) {
      setLoading(false);
      setError(canView ? "Invalid order" : "Missing permission: fulfillment.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchFulfillmentOrder(id);
      setOrder(res.data);
      const next: Record<number, string> = {};
      for (const item of res.data.items) {
        next[item.id] = String(item.picked);
      }
      setPicked(next);
      if (res.data.fulfillment.package_count) {
        setPackageCount(String(res.data.fulfillment.package_count));
      }
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

  async function savePick(e: FormEvent) {
    e.preventDefault();
    if (!order) return;
    const items = order.items.map((item) => ({
      order_item_id: item.id,
      picked: Math.max(0, Math.floor(Number(picked[item.id]) || 0)),
    }));
    await run(() => updatePick(id, items), "Pick quantities saved");
  }

  if (loading) return <LoadingBlock />;
  if (error || !order) return <ErrorState message={error ?? "Not found"} onRetry={() => void load()} />;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Fulfillment", href: "/fulfillment" },
          { label: order.order_number },
        ]}
      />
      <PageHeader
        title={order.order_number}
        description={`${order.customer.name ?? ""} · ${order.customer.email ?? ""}`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Badge tone={statusTone(order.status)}>{order.status.replaceAll("_", " ")}</Badge>
            <Link href={`/orders/${order.id}`}>
              <Button type="button" variant="secondary">
                Order record
              </Button>
            </Link>
          </div>
        }
      />

      <div className="mb-4 flex flex-wrap gap-2">
        {order.actions.can_start_picking && canPick ? (
          <Button type="button" disabled={busy} onClick={() => void run(() => startPicking(id), "Picking started")}>
            Start picking
          </Button>
        ) : null}
        {order.actions.can_complete_pick && canPick ? (
          <Button
            type="button"
            disabled={busy}
            onClick={() => void run(() => completePicking(id), "Picking completed")}
          >
            Complete pick
          </Button>
        ) : null}
        {order.actions.can_out_for_delivery && canShip ? (
          <Button
            type="button"
            disabled={busy}
            onClick={() => void run(() => markOutForDelivery(id), "Out for delivery")}
          >
            Out for delivery
          </Button>
        ) : null}
        {order.actions.can_deliver && canShip ? (
          <Button type="button" disabled={busy} onClick={() => void run(() => markDelivered(id), "Delivered")}>
            Mark delivered
          </Button>
        ) : null}
        {order.actions.can_retry_delivery && canShip ? (
          <Button type="button" disabled={busy} onClick={() => void run(() => retryDelivery(id), "Retry started")}>
            Retry delivery
          </Button>
        ) : null}
      </div>

      <FormSection title="Line items" description="Required = ordered qty (stock already committed at payment).">
        <form onSubmit={savePick}>
          <table className="mb-3 min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-2 py-2">Product</th>
                <th className="px-2 py-2">SKU</th>
                <th className="px-2 py-2">Required</th>
                <th className="px-2 py-2">Picked</th>
                <th className="px-2 py-2">Exception</th>
              </tr>
            </thead>
            <tbody>
              {order.items.map((item) => (
                <tr key={item.id} className="border-b border-[var(--admin-border)]/60">
                  <td className="px-2 py-2 font-medium">{item.name}</td>
                  <td className="px-2 py-2 font-mono text-xs">{item.sku}</td>
                  <td className="px-2 py-2">{item.required}</td>
                  <td className="px-2 py-2">
                    <Input
                      type="number"
                      min="0"
                      max={item.required}
                      value={picked[item.id] ?? "0"}
                      disabled={!order.actions.can_update_pick || !canPick}
                      onChange={(e) => setPicked((p) => ({ ...p, [item.id]: e.target.value }))}
                    />
                  </td>
                  <td className="px-2 py-2">
                    {order.actions.can_update_pick && canPick ? (
                      <button
                        type="button"
                        className="text-xs text-[var(--admin-primary)] hover:underline"
                        onClick={() => {
                          setExcItem(item.id);
                          setExcActual(String(Math.min(item.required - 1, item.picked)));
                          setExcNote("");
                        }}
                      >
                        Short pick
                      </button>
                    ) : (
                      "—"
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {order.actions.can_update_pick && canPick ? (
            <Button type="submit" disabled={busy}>
              Save pick quantities
            </Button>
          ) : null}
        </form>
      </FormSection>

      {order.actions.can_pack && canPack ? (
        <div className="mt-4">
          <FormSection title="Pack">
            <div className="grid max-w-md gap-3 sm:grid-cols-2">
              <Input
                label="Package count"
                type="number"
                min="1"
                value={packageCount}
                onChange={(e) => setPackageCount(e.target.value)}
              />
              <Input
                label="Weight (grams)"
                type="number"
                min="0"
                value={weight}
                onChange={(e) => setWeight(e.target.value)}
              />
              <Button
                type="button"
                disabled={busy}
                onClick={() =>
                  void run(
                    () =>
                      packOrder(id, {
                        package_count: Number(packageCount) || 1,
                        weight_grams: weight ? Number(weight) : undefined,
                      }),
                    "Packed",
                  )
                }
              >
                Complete packing
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}

      {order.actions.can_ship && canShip ? (
        <div className="mt-4">
          <FormSection title="Create shipment" description="Leave tracking blank to auto-generate via Internal Delivery provider.">
            <div className="grid max-w-xl gap-3 sm:grid-cols-2">
              <Input label="Carrier" value={carrier} onChange={(e) => setCarrier(e.target.value)} placeholder="GreenLeaf Delivery" />
              <Input label="Tracking number" value={tracking} onChange={(e) => setTracking(e.target.value)} />
              <Input label="ETA" type="date" value={eta} onChange={(e) => setEta(e.target.value)} />
              <div className="flex items-end">
                <Button type="button" disabled={busy} onClick={() => setConfirmShip(true)}>
                  Create shipment
                </Button>
              </div>
            </div>
          </FormSection>
        </div>
      ) : null}

      {order.actions.can_fail_delivery && canShip ? (
        <div className="mt-4">
          <FormSection title="Delivery failure">
            <div className="grid max-w-xl gap-3 sm:grid-cols-2">
              <Select label="Reason" value={failReason} onChange={(e) => setFailReason(e.target.value)}>
                <option value="CUSTOMER_UNAVAILABLE">Customer unavailable</option>
                <option value="WRONG_ADDRESS">Wrong address</option>
                <option value="DAMAGED_PACKAGE">Damaged package</option>
                <option value="DELIVERY_AREA_ISSUE">Delivery area issue</option>
                <option value="COURIER_FAILURE">Courier failure</option>
                <option value="OTHER">Other</option>
              </Select>
              <TextArea label="Note" value={failNote} onChange={(e) => setFailNote(e.target.value)} />
              <Button
                type="button"
                variant="danger"
                disabled={busy}
                onClick={() =>
                  void run(() => failDelivery(id, failReason, failNote || undefined), "Failure recorded")
                }
              >
                Record failure
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}

      {(order.fulfillment.exceptions ?? []).length > 0 ? (
        <div className="mt-4">
          <FormSection title="Exceptions">
            <ul className="space-y-2 text-sm">
              {(order.fulfillment.exceptions ?? []).map((ex) => (
                <li key={ex.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--admin-border)]/60 pb-2">
                  <div>
                    <div className="font-medium">
                      {ex.type} · {ex.status}
                    </div>
                    <div className="text-xs text-[var(--admin-muted)]">
                      {ex.reason ?? ex.note} {ex.at ? `· ${formatDateTime(ex.at)}` : ""}
                    </div>
                  </div>
                  {ex.status === "open" && canManage ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      disabled={busy}
                      onClick={() =>
                        void run(() => resolveException(id, ex.id, "Resolved in Admin"), "Exception resolved")
                      }
                    >
                      Resolve
                    </Button>
                  ) : null}
                </li>
              ))}
            </ul>
          </FormSection>
        </div>
      ) : null}

      {order.shipment ? (
        <div className="mt-4">
          <FormSection title="Shipment">
            <p className="text-sm">
              {order.shipment.carrier} · {order.shipment.tracking_number} · {order.shipment.status}
            </p>
            <Link
              href={`/fulfillment/shipments/${order.shipment.id}`}
              className="mt-2 inline-block text-sm text-[var(--admin-primary)] hover:underline"
            >
              View shipment timeline →
            </Link>
          </FormSection>
        </div>
      ) : null}

      <ConfirmDialog
        open={confirmShip}
        title="Create shipment?"
        description="Transitions PACKED → SHIPPED and notifies the customer. Retry-safe if already shipped."
        confirmLabel="Ship"
        loading={busy}
        onCancel={() => setConfirmShip(false)}
        onConfirm={() => {
          setConfirmShip(false);
          void run(
            () =>
              shipOrder(id, {
                carrier: carrier || undefined,
                tracking_number: tracking || undefined,
                eta_date: eta || undefined,
              }),
            "Shipment created",
          );
        }}
      />

      <ConfirmDialog
        open={excItem != null}
        title="Record short-pick exception"
        description="Does not silently rewrite inventory. Record discrepancy, then adjust stock via Inventory if needed."
        confirmLabel="Record"
        loading={busy}
        onCancel={() => setExcItem(null)}
        onConfirm={() => {
          const item = order.items.find((i) => i.id === excItem);
          if (!item || !excNote.trim()) {
            push("Note is required", "error");
            return;
          }
          void run(
            () =>
              recordPickException(id, {
                order_item_id: item.id,
                expected: item.required,
                actual: Math.max(0, Math.floor(Number(excActual) || 0)),
                note: excNote.trim(),
              }).then(() => setExcItem(null)),
            "Exception recorded",
          );
        }}
      />
    </div>
  );
}
