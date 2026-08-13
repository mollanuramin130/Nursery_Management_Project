"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchOrder, updateOrderStatus } from "@/lib/api/orders";
import { hasPermission } from "@/lib/auth/permissions";
import {
  allowedOrderTransitions,
  orderStatusLabel,
  statusTone,
} from "@/lib/auth/order-transitions";
import { paymentStatusLabel } from "@/lib/payment-status";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { AdminOrderDetail } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function addressLines(address?: Record<string, unknown> | null) {
  if (!address) return ["—"];
  const keys = [
    "name",
    "line1",
    "line2",
    "address_line1",
    "address_line2",
    "city",
    "state",
    "postal_code",
    "pincode",
    "phone",
  ];
  const lines = keys
    .map((k) => address[k])
    .filter((v): v is string => typeof v === "string" && v.trim().length > 0);
  if (lines.length) return lines;
  return [JSON.stringify(address)];
}

export default function OrderDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "orders.view");
  const canUpdate = hasPermission(user, "orders.update_status");
  const push = useToastStore((s) => s.push);

  const [order, setOrder] = useState<AdminOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nextStatus, setNextStatus] = useState("");
  const [note, setNote] = useState("");
  const [carrier, setCarrier] = useState("");
  const [trackingNumber, setTrackingNumber] = useState("");
  const [trackingUrl, setTrackingUrl] = useState("");

  const transitions = useMemo(
    () => (order ? allowedOrderTransitions(order.status) : []),
    [order],
  );

  const load = useCallback(async () => {
    if (!canView || !Number.isFinite(id)) {
      setLoading(false);
      setError("Missing permission or invalid order id");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchOrder(id);
      setOrder(res.data);
      const next = allowedOrderTransitions(res.data.status);
      setNextStatus(next[0] ?? "");
      setCarrier(res.data.shipment?.carrier ?? "");
      setTrackingNumber(res.data.shipment?.tracking_number ?? "");
      setTrackingUrl(res.data.shipment?.tracking_url ?? "");
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onStatusSubmit(e: FormEvent) {
    e.preventDefault();
    if (!order || !nextStatus) return;
    setSaving(true);
    try {
      await updateOrderStatus(order.id, {
        status: nextStatus,
        note: note || undefined,
        carrier: carrier || undefined,
        tracking_number: trackingNumber || undefined,
        tracking_url: trackingUrl || undefined,
      });
      push("Order status updated", "success");
      setNote("");
      await load();
    } catch (err) {
      push(
        err instanceof ApiError || err instanceof Error ? err.message : "Update failed",
        "error",
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Orders", href: "/orders" },
          { label: order?.order_number ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={order?.order_number ?? "Order detail"}
        description="Server-authoritative order state and history."
        actions={
          order ? (
            <Badge tone={statusTone(order.status)}>
              {orderStatusLabel(order.status)}
            </Badge>
          ) : undefined
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && order ? (
        <div className="grid gap-4 xl:grid-cols-[1.4fr_0.8fr]">
          <div className="space-y-4">
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Order information</h2>
              <dl className="grid gap-2 sm:grid-cols-2 text-sm">
                <div>
                  <dt className="text-xs text-[var(--admin-muted)]">Order number</dt>
                  <dd>{order.order_number}</dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--admin-muted)]">Created</dt>
                  <dd>{formatDateTime(order.created_at)}</dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--admin-muted)]">Payment method</dt>
                  <dd>
                    {(order.payment_method ?? "—").toUpperCase()}
                    {order.payment?.upi_mode ? ` · ${order.payment.upi_mode}` : ""}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--admin-muted)]">Payment status</dt>
                  {/* QA-36-006: human label (parity with list + payment card) */}
                  <dd>{paymentStatusLabel(order.payment?.status)}</dd>
                </div>
              </dl>
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Customer</h2>
              <div className="text-sm">
                <div className="font-medium">{order.customer.name ?? "—"}</div>
                <div className="text-[var(--admin-muted)]">{order.customer.email}</div>
                <div className="text-[var(--admin-muted)]">{order.customer.phone ?? "No phone"}</div>
              </div>
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Items</h2>
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-2 py-2 font-medium">Product</th>
                      <th className="px-2 py-2 font-medium">SKU</th>
                      <th className="px-2 py-2 font-medium">Qty</th>
                      <th className="px-2 py-2 font-medium">Unit</th>
                      <th className="px-2 py-2 font-medium">Line total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {order.items.map((item) => (
                      <tr key={item.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-2 py-2">{item.name}</td>
                        <td className="px-2 py-2 font-mono text-xs">{item.sku}</td>
                        <td className="px-2 py-2">{item.quantity}</td>
                        <td className="px-2 py-2">{formatMoney(item.unit_price)}</td>
                        <td className="px-2 py-2">{formatMoney(item.line_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="mt-2 text-xs text-[var(--admin-muted)]">
                Line discount fields are not returned by the Admin order detail API.
              </p>
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Timeline</h2>
              <ol className="space-y-3">
                {order.status_history.map((h, idx) => (
                  <li key={`${h.to}-${idx}`} className="flex gap-3 text-sm">
                    <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--admin-primary)]" />
                    <div>
                      <div className="font-medium">
                        {h.from ? `${h.from} → ${h.to}` : h.to}
                      </div>
                      <div className="text-xs text-[var(--admin-muted)]">
                        {formatDateTime(h.at)}
                        {h.note ? ` · ${h.note}` : ""}
                      </div>
                    </div>
                  </li>
                ))}
              </ol>
            </section>
          </div>

          <div className="space-y-4">
            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Totals</h2>
              <dl className="space-y-1.5 text-sm">
                <div className="flex justify-between">
                  <dt>Subtotal</dt>
                  <dd>{formatMoney(order.subtotal)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>Discount</dt>
                  <dd>{formatMoney(order.discount_total)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>Tax</dt>
                  <dd>{formatMoney(order.tax_total)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt>Shipping</dt>
                  <dd>{formatMoney(order.shipping_total)}</dd>
                </div>
                <div className="flex justify-between border-t border-[var(--admin-border)] pt-2 font-semibold">
                  <dt>Grand total</dt>
                  <dd>{formatMoney(order.grand_total)}</dd>
                </div>
              </dl>
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Payment</h2>
              {order.payment ? (
                <dl className="space-y-1 text-sm">
                  <div>Method: {(order.payment.method ?? "—").toUpperCase()}</div>
                  {order.payment.upi_mode ? (
                    <div>UPI mode: {order.payment.upi_mode}</div>
                  ) : null}
                  <div>ID: {order.payment.id}</div>
                  <div>Amount: {formatMoney(order.payment.amount)}</div>
                  <div>Status: {paymentStatusLabel(order.payment.status)}</div>
                  <div>Paid: {formatDateTime(order.payment.paid_at)}</div>
                  {order.payment.provider_payment_id ? (
                    <div className="break-all font-mono text-xs">
                      Txn: {order.payment.provider_payment_id}
                    </div>
                  ) : null}
                  {order.payment.provider_order_id ? (
                    <div className="break-all font-mono text-xs">
                      Provider order: {order.payment.provider_order_id}
                    </div>
                  ) : null}
                </dl>
              ) : (
                <p className="text-sm text-[var(--admin-muted)]">No payment record.</p>
              )}
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Shipping</h2>
              <div className="space-y-1 text-sm">
                {addressLines(order.shipping_address).map((line) => (
                  <div key={line}>{line}</div>
                ))}
                <div className="pt-2 text-xs text-[var(--admin-muted)]">
                  Carrier: {order.shipment?.carrier ?? "—"}
                  <br />
                  Tracking: {order.shipment?.tracking_number ?? "—"}
                </div>
              </div>
            </section>

            <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold">Update status</h2>
              {!canUpdate ? (
                <p className="text-sm text-[var(--admin-muted)]">
                  Requires orders.update_status.
                </p>
              ) : transitions.length === 0 ? (
                <p className="text-sm text-[var(--admin-muted)]">No further transitions allowed.</p>
              ) : (
                <form className="space-y-3" onSubmit={onStatusSubmit}>
                  <Select
                    label="Next status"
                    value={nextStatus}
                    onChange={(e) => setNextStatus(e.target.value)}
                    required
                  >
                    {transitions.map((s) => (
                      <option key={s} value={s}>
                        {orderStatusLabel(s)}
                      </option>
                    ))}
                  </Select>
                  <TextArea
                    label="Note"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="Optional note"
                  />
                  {["SHIPPED", "OUT_FOR_DELIVERY", "DELIVERED"].includes(nextStatus) ? (
                    <>
                      <Input
                        label="Carrier"
                        value={carrier}
                        onChange={(e) => setCarrier(e.target.value)}
                      />
                      <Input
                        label="Tracking number"
                        value={trackingNumber}
                        onChange={(e) => setTrackingNumber(e.target.value)}
                      />
                      <Input
                        label="Tracking URL"
                        value={trackingUrl}
                        onChange={(e) => setTrackingUrl(e.target.value)}
                      />
                    </>
                  ) : null}
                  <Button type="submit" disabled={saving}>
                    {saving ? "Updating…" : "Apply status"}
                  </Button>
                </form>
              )}
            </section>
          </div>
        </div>
      ) : null}
    </div>
  );
}
