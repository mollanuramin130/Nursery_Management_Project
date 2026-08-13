"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useParams, useSearchParams } from "next/navigation";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import type { Cart, OrderDetail } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { cn } from "@/lib/cn";
import { ORDER_STATUS_LABELS, orderStatusLabel, orderStatusTone } from "@/lib/order-status";
import { paymentStatusLabel } from "@/lib/payment-status";

const CANCEL_REASONS = [
  { code: "changed_mind", label: "Changed my mind" },
  { code: "ordered_by_mistake", label: "Ordered by mistake" },
  { code: "better_price", label: "Found a better price" },
  { code: "delivery_slow", label: "Delivery is taking too long" },
  { code: "no_longer_needed", label: "Product no longer needed" },
  { code: "other", label: "Other" },
] as const;

const FLOW = [
  "PENDING_PAYMENT",
  "CONFIRMED",
  "PROCESSING",
  "PACKED",
  "SHIPPED",
  "OUT_FOR_DELIVERY",
  "DELIVERED",
] as const;

const LABELS = ORDER_STATUS_LABELS;

export function OrderDetailClient() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  // QA-36-003: NaN / non-positive ids must not hang on infinite skeleton.
  const orderIdValid = Number.isInteger(id) && id > 0;
  const search = useSearchParams();
  const placed = search.get("placed");
  const paid = search.get("paid");
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const toast = useToastStore((s) => s.push);
  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [returnOpen, setReturnOpen] = useState(false);
  const [reasonCode, setReasonCode] = useState<string>("changed_mind");
  const [reasonOther, setReasonOther] = useState("");
  const [returnReason, setReturnReason] = useState("damaged");
  const [returnNotes, setReturnNotes] = useState("");
  const [returnItemId, setReturnItemId] = useState<number | null>(null);
  const [returnQty, setReturnQty] = useState(1);

  async function reload() {
    const res = await apiGet<OrderDetail>(`/orders/${id}`);
    setOrder(res.data);
  }

  async function retryPayment() {
    if (!order || busy) return;
    setBusy(true);
    try {
      const { openRazorpayCheckout, localStubPayment } = await import("@/lib/razorpay");
      const pay = await apiSend<{
        payment_id: number;
        provider_order_id: string;
        client_payload: {
          key: string;
          order_id: string;
          amount: number;
          currency: string;
          name?: string;
          mode?: string;
          prefill?: { name?: string; email?: string; contact?: string };
        };
      }>("post", `/orders/${order.id}/retry-payment`);

      const payload = pay.data.client_payload;
      const providerOrderId = pay.data.provider_order_id || payload.order_id;
      const gatewayResult =
        payload.mode === "local_stub"
          ? localStubPayment(providerOrderId, pay.data.payment_id)
          : await openRazorpayCheckout({
              key: payload.key,
              amount: payload.amount,
              currency: payload.currency || "INR",
              name: payload.name || "GreenLeaf Nursery",
              description: `Order ${order.order_number}`,
              order_id: providerOrderId,
              prefill: payload.prefill,
            });

      const verified = await apiSend<{ payment_status: string; order_status: string }>(
        "post",
        "/payments/verify",
        {
          payment_id: pay.data.payment_id,
          provider_order_id: gatewayResult.razorpay_order_id,
          provider_payment_id: gatewayResult.razorpay_payment_id,
          provider_signature: gatewayResult.razorpay_signature,
        },
      );
      if (verified.data.payment_status !== "success") {
        throw new Error("Payment could not be confirmed");
      }
      toast("Payment successful");
      await reload();
    } catch (e) {
      toast(e instanceof Error ? e.message : "Payment retry failed", "error");
      try {
        await reload();
      } catch {
        /* ignore */
      }
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    if (!bootstrapped || !user || !orderIdValid) return;
    void apiGet<OrderDetail>(`/orders/${id}`)
      .then((res) => setOrder(res.data))
      .catch((e) => setError(e instanceof Error ? e.message : "Failed to load order"));
  }, [bootstrapped, user, id, orderIdValid]);

  const activeIndex = useMemo(() => {
    if (!order) return -1;
    if (order.status === "CANCELLED") return -1;
    const idx = FLOW.indexOf(order.status as (typeof FLOW)[number]);
    if (idx >= 0) return idx;
    // map pending variants
    if (order.status === "PAYMENT_FAILED") return 0;
    return FLOW.findIndex((s) =>
      (order.status_history ?? []).some((h) => h.status === s),
    );
  }, [order]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-3xl space-y-3">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-40 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    const orderPath = params?.id
      ? `/account/orders/${params.id}`
      : "/account/orders";
    return (
      <EmptyState
        title="Sign in to track orders"
        description="Order tracking is available on your account."
        actionHref={loginHref(orderPath)}
        actionLabel="Sign in"
      />
    );
  }

  if (!orderIdValid) {
    return (
      <EmptyState
        title="Order not found"
        description="This order link is invalid."
        actionHref="/account/orders"
        actionLabel="Back to orders"
      />
    );
  }

  if (error) {
    return (
      <EmptyState
        title="Couldn’t load this order"
        description={error}
        actionHref="/account/orders"
        actionLabel="Back to orders"
      />
    );
  }

  if (!order) {
    return (
      <section className="section">
        <div className="container max-w-3xl space-y-3">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-40 w-full" />
        </div>
      </section>
    );
  }

  const canCancel = order.actions?.can_cancel ?? order.can_cancel ?? false;
  const canReorder = order.actions?.can_reorder ?? order.can_reorder ?? false;
  const canReturn = order.actions?.can_return ?? order.can_return ?? false;
  const timeline = order.tracking?.timeline;
  const returnableItems = order.returnable_items ?? [];
  const returnableByItemId = Object.fromEntries(
    returnableItems.map((r) => [r.order_item_id, r.returnable_qty]),
  );
  const returnableOptions =
    returnableItems.length > 0
      ? order.items.filter((i) => (returnableByItemId[i.id] ?? 0) > 0)
      : order.items;
  const returnReasons = order.return_reasons ?? [
    { code: "damaged", label: "Damaged or unhealthy plant" },
    { code: "wrong_item", label: "Wrong item received" },
    { code: "not_as_described", label: "Not as described" },
    { code: "changed_mind", label: "Changed my mind" },
    { code: "other", label: "Other" },
  ];
  const selectedReturnItem =
    returnableOptions.find((i) => i.id === returnItemId) ?? returnableOptions[0] ?? null;
  const maxReturnQty =
    selectedReturnItem != null
      ? (returnableByItemId[selectedReturnItem.id] ?? selectedReturnItem.quantity)
      : 1;

  return (
    <section className="section">
      <div className="container max-w-3xl">
        <nav className="mb-4 text-sm text-[var(--color-muted)]">
          <Link href="/account/orders" className="hover:text-[var(--color-primary)]">
            Orders
          </Link>
          <span className="mx-2">/</span>
          <span>{order.order_number}</span>
        </nav>

        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">Order tracking</h1>
            <p className="mt-1 text-[var(--color-muted)]">{order.order_number}</p>
          </div>
          <Badge tone={orderStatusTone(order.status)}>{orderStatusLabel(order.status)}</Badge>
        </div>

        {/* QA-35-004: success only from server CONFIRMED — never trust paid=1 alone */}
        {order.status === "CONFIRMED" ? (
          <div className="mt-6 rounded-[var(--radius-lg)] border border-[var(--color-success)] bg-[var(--color-success-soft)] p-4">
            <p className="font-semibold text-[var(--color-success)]">
              {paid === "1" || placed
                ? "✓ Payment successful — order confirmed"
                : "✓ Order confirmed"}
            </p>
            <p className="mt-1 text-sm">
              Order {order.order_number} · {money(order.grand_total, order.currency)}. We’ll update this page as your
              plants move through packing and delivery.
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Link
                href="/shop"
                className="inline-flex min-h-10 items-center rounded-[var(--radius-full)] bg-[var(--color-secondary-soft)] px-3.5 text-sm font-semibold"
              >
                Continue shopping
              </Link>
            </div>
          </div>
        ) : null}

        {/* QA-36-002: unpaid banner only from authoritative PENDING_PAYMENT status */}
        {order.status === "PENDING_PAYMENT" ? (
          <div className="mt-6 rounded-[var(--radius-lg)] border border-[var(--color-warning)] bg-[var(--color-warning-soft)] p-4">
            <p className="font-semibold">Order placed</p>
            <p className="mt-1 text-sm">Complete payment to confirm this order. Your cart is still available if you cancel.</p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Button size="sm" disabled={busy} onClick={() => void retryPayment()}>
                {busy ? "Opening…" : `Pay ${money(order.grand_total, order.currency)}`}
              </Button>
              <Button size="sm" variant="secondary" disabled={busy} onClick={() => setCancelOpen(true)}>
                Cancel order
              </Button>
            </div>
          </div>
        ) : null}

        {order.status === "PAYMENT_FAILED" ? (
          <div className="mt-6 rounded-[var(--radius-lg)] border border-[var(--color-error)] bg-[var(--color-error-soft)] p-4">
            <p className="font-semibold text-[var(--color-error)]">Payment could not be completed</p>
            <p className="mt-1 text-sm">
              No charge is confirmed until the payment status succeeds on our servers. You can try again,
              use another method after retry, or return to your cart.
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Button size="sm" disabled={busy} onClick={() => void retryPayment()}>
                {busy ? "Opening…" : "Try again"}
              </Button>
              <Link
                href="/cart"
                className="inline-flex min-h-10 items-center rounded-[var(--radius-full)] bg-[var(--color-secondary-soft)] px-3.5 text-sm font-semibold"
              >
                Return to cart
              </Link>
              <Link
                href="/shop"
                className="inline-flex min-h-10 items-center rounded-[var(--radius-full)] border border-[var(--color-border)] px-3.5 text-sm font-semibold"
              >
                Continue shopping
              </Link>
            </div>
          </div>
        ) : null}

        {order.status === "CANCELLED" ? (
          <div className="mt-6 rounded-[var(--radius-lg)] border border-[var(--color-error)] bg-[var(--color-error-soft)] p-4 text-sm">
            <p className="font-semibold">Order cancelled</p>
            {order.cancel_reason ? <p className="mt-1">Reason: {order.cancel_reason}</p> : null}
            {order.cancelled_at ? (
              <p className="mt-1 text-[var(--color-muted)]">
                Cancelled on {new Date(order.cancelled_at).toLocaleString("en-IN")}
              </p>
            ) : null}
            <p className="mt-2">
              Payment:{" "}
              {order.payment_method === "cod" || order.payment?.status === "cod"
                ? "Not required (COD)"
                : paymentStatusLabel(order.payment?.status)}
            </p>
          </div>
        ) : (
          <ol className="mt-8 space-y-0 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
            {(timeline && timeline.length
              ? timeline.map((step, i) => ({
                  key: step.status,
                  title: step.title,
                  done: step.completed,
                  current: !!step.current,
                  at: step.created_at,
                  last: i === timeline.length - 1,
                }))
              : FLOW.map((step, i) => ({
                  key: step,
                  title: LABELS[step] ?? step,
                  done: activeIndex >= i,
                  current: activeIndex === i,
                  at: order.status_history?.find((h) => h.status === step)?.at,
                  last: i === FLOW.length - 1,
                }))
            ).map((step) => (
              <li key={step.key} className="flex gap-3">
                <div className="flex flex-col items-center">
                  <span
                    className={cn(
                      "flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold",
                      step.done
                        ? "bg-[var(--color-primary-deep)] text-white"
                        : "bg-[var(--color-surface-muted)] text-[var(--color-muted)]",
                      step.current && "ring-2 ring-[var(--color-primary)] ring-offset-2",
                    )}
                  >
                    {step.done ? "✓" : "○"}
                  </span>
                  {!step.last ? (
                    <span
                      className={cn(
                        "my-1 w-0.5 flex-1 min-h-6",
                        step.done ? "bg-[var(--color-primary)]" : "bg-[var(--color-border)]",
                      )}
                    />
                  ) : null}
                </div>
                <div className="pb-5">
                  <p className={cn("font-semibold", step.current && "text-[var(--color-primary-deep)]")}>
                    {step.title}
                  </p>
                  {step.at ? (
                    <p className="text-xs text-[var(--color-muted)]">
                      {new Date(step.at).toLocaleString("en-IN")}
                    </p>
                  ) : null}
                </div>
              </li>
            ))}
          </ol>
        )}

        <div className="mt-8 grid gap-4 md:grid-cols-2">
          <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
            <h2 className="font-semibold">Delivery address</h2>
            {order.shipping_address ? (
              <p className="mt-2 text-sm leading-relaxed text-[var(--color-ink-soft)]">
                {order.shipping_address.name}
                <br />
                {order.shipping_address.line1}
                {order.shipping_address.line2 ? (
                  <>
                    <br />
                    {order.shipping_address.line2}
                  </>
                ) : null}
                <br />
                {order.shipping_address.city}, {order.shipping_address.state}{" "}
                {order.shipping_address.postal_code}
              </p>
            ) : (
              <p className="mt-2 text-sm text-[var(--color-muted)]">No address on file.</p>
            )}
          </div>
          <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
            <h2 className="font-semibold">Payment</h2>
            <p className="mt-2 text-sm capitalize text-[var(--color-ink-soft)]">
              {order.payment?.method ?? "—"} · {order.payment?.status ?? "—"}
            </p>
            {(order.shipment?.carrier || order.tracking?.shipment?.carrier) && (
              <p className="mt-3 text-sm text-[var(--color-ink-soft)]">
                Carrier: {order.shipment?.carrier ?? order.tracking?.shipment?.carrier}
              </p>
            )}
            {order.shipment?.tracking_number || order.tracking?.shipment?.tracking_number ? (
              <p className="mt-1 text-sm">
                Tracking:{" "}
                {(order.shipment?.tracking_url || order.tracking?.shipment?.tracking_url) ? (
                  <a
                    href={
                      (order.shipment?.tracking_url ||
                        order.tracking?.shipment?.tracking_url) as string
                    }
                    className="font-semibold text-[var(--color-primary)]"
                    target="_blank"
                    rel="noreferrer"
                  >
                    {order.shipment?.tracking_number ?? order.tracking?.shipment?.tracking_number}
                  </a>
                ) : (
                  order.shipment?.tracking_number ?? order.tracking?.shipment?.tracking_number
                )}
              </p>
            ) : null}
            {order.tracking?.estimated_delivery ? (
              <p className="mt-1 text-sm text-[var(--color-ink-soft)]">
                Estimated delivery: {order.tracking.estimated_delivery}
              </p>
            ) : null}
            {(order.tracking?.events?.length ?? 0) > 0 ? (
              <ul className="mt-3 space-y-1 border-t border-[var(--color-border)] pt-3 text-xs text-[var(--color-ink-soft)]">
                {order.tracking!.events!.slice(-5).map((ev, i) => (
                  <li key={`${ev.status}-${i}`}>
                    {ev.event_at
                      ? new Date(ev.event_at).toLocaleString("en-IN", {
                          dateStyle: "medium",
                          timeStyle: "short",
                        })
                      : ""}{" "}
                    · {ev.status}
                    {ev.description ? ` — ${ev.description}` : ""}
                  </li>
                ))}
              </ul>
            ) : null}
            {order.refunds?.length ? (
              <div className="mt-4 border-t border-[var(--color-border)] pt-3">
                <p className="text-sm font-semibold">Refunds</p>
                <ul className="mt-2 space-y-1 text-sm text-[var(--color-ink-soft)]">
                  {order.refunds.map((r) => (
                    <li key={r.id}>
                      {money(r.amount)} · {r.status.replace(/_/g, " ")}
                      {r.created_at
                        ? ` · ${new Date(r.created_at).toLocaleDateString("en-IN")}`
                        : ""}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        </div>

        {order.returns?.length ? (
          <div className="mt-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
            <h2 className="font-semibold">Returns</h2>
            <ul className="mt-2 space-y-3 text-sm">
              {order.returns.map((r) => (
                <li key={r.id} className="rounded border border-[var(--color-border)] p-3">
                  <div className="flex justify-between gap-3">
                    <span className="font-medium">Return #{r.id}</span>
                    <Badge tone="brand">{r.status.replace(/_/g, " ")}</Badge>
                  </div>
                  {r.pickup_tracking?.tracking_number ? (
                    <p className="mt-1 text-xs text-[var(--color-muted)]">
                      Pickup {r.pickup_tracking.carrier ?? "carrier"} · {r.pickup_tracking.tracking_number}
                    </p>
                  ) : null}
                  {r.timeline?.length ? (
                    <ol className="mt-2 space-y-1 text-xs text-[var(--color-muted)]">
                      {r.timeline.slice(-4).map((t, idx) => (
                        <li key={`${r.id}-${idx}`}>
                          {(t.status ?? "").replace(/_/g, " ")}
                          {t.at ? ` · ${new Date(t.at).toLocaleString("en-IN")}` : ""}
                        </li>
                      ))}
                    </ol>
                  ) : null}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <div className="mt-8 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
          <h2 className="font-semibold">Items</h2>
          <ul className="mt-3 space-y-3">
            {order.items.map((item) => (
              <li key={item.id} className="flex gap-3">
                <div className="relative h-16 w-16 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                  {item.thumbnail_url ? (
                    <Image src={item.thumbnail_url} alt="" fill className="object-cover" sizes="64px" />
                  ) : null}
                </div>
                <div className="flex-1">
                  <p className="font-medium">{item.name}</p>
                  <p className="text-sm text-[var(--color-muted)]">Qty {item.quantity}</p>
                  {order.reviewable_product_ids?.includes(item.product_id ?? -1) && item.product_slug ? (
                    <Link
                      href={`/product/${item.product_slug}?review=1`}
                      className="mt-1 inline-block text-xs font-medium text-[var(--color-primary-deep)] hover:underline"
                    >
                      Write a review
                    </Link>
                  ) : order.reviewable_product_ids?.includes(item.product_id ?? -1) ? (
                    <p className="mt-1 text-xs font-medium text-[var(--color-primary-deep)]">
                      Eligible to review on the product page
                    </p>
                  ) : null}
                </div>
                <p className="font-semibold">{money(item.line_total)}</p>
              </li>
            ))}
          </ul>
          <dl className="mt-4 space-y-1 border-t border-[var(--color-border)] pt-4 text-sm">
            <Row label="Subtotal" value={money(order.subtotal)} />
            <Row label="Discount" value={money(-order.discount_total)} />
            <Row label="Delivery" value={money(order.shipping_total)} />
            <Row label="Tax" value={money(order.tax_total)} />
            <Row label="Total" value={money(order.grand_total)} strong />
          </dl>
        </div>

        <div className="mt-6 flex flex-wrap gap-3">
          <Link href="/shop">
            <Button variant="outline">Continue shopping</Button>
          </Link>
          {canReorder ? (
            <Button
              variant="secondary"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                try {
                  const res = await apiSend<{
                    cart: Cart;
                    reorder_summary: {
                      added: unknown[];
                      unavailable: unknown[];
                      price_changed: unknown[];
                    };
                  }>("post", `/orders/${order.id}/reorder`);
                  useCartStore.setState({ cart: res.data.cart });
                  const s = res.data.reorder_summary;
                  toast(
                    `Reorder: ${s.added.length} added` +
                      (s.unavailable.length ? `, ${s.unavailable.length} unavailable` : "") +
                      (s.price_changed.length ? `, ${s.price_changed.length} price changed` : ""),
                  );
                } catch (e) {
                  toast(e instanceof Error ? e.message : "Reorder failed", "error");
                } finally {
                  setBusy(false);
                }
              }}
            >
              Reorder
            </Button>
          ) : null}
          {canReturn && returnableOptions.length > 0 ? (
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => {
                const first = returnableOptions[0];
                setReturnItemId(first?.id ?? null);
                setReturnQty(1);
                setReturnReason(returnReasons[0]?.code ?? "damaged");
                setReturnNotes("");
                setReturnOpen(true);
              }}
            >
              Return item
            </Button>
          ) : null}
          {canCancel ? (
            <Button variant="danger" disabled={busy} onClick={() => setCancelOpen(true)}>
              Cancel order
            </Button>
          ) : null}
        </div>

        {returnOpen && selectedReturnItem ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-[var(--radius-lg)] bg-white p-5 shadow-lg">
              <h2 className="text-lg font-bold">Request a return</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">{order.order_number}</p>
              <div className="mt-4 space-y-3">
                <label className="block text-sm font-medium">
                  Item
                  <select
                    className="mt-1 w-full rounded border border-[var(--color-border)] px-3 py-2"
                    value={returnItemId ?? selectedReturnItem.id}
                    onChange={(e) => {
                      const id = Number(e.target.value);
                      setReturnItemId(id);
                      setReturnQty(1);
                    }}
                  >
                    {returnableOptions.map((i) => (
                      <option key={i.id} value={i.id}>
                        {i.name} (returnable {returnableByItemId[i.id] ?? i.quantity})
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm font-medium">
                  Quantity
                  <input
                    type="number"
                    min={1}
                    max={maxReturnQty}
                    className="mt-1 w-full rounded border border-[var(--color-border)] px-3 py-2"
                    value={returnQty}
                    onChange={(e) =>
                      setReturnQty(
                        Math.max(1, Math.min(maxReturnQty, Number(e.target.value) || 1)),
                      )
                    }
                  />
                </label>
                <fieldset className="space-y-2">
                  <legend className="text-sm font-medium">Reason</legend>
                  {returnReasons.map((r) => (
                    <label key={r.code} className="flex cursor-pointer items-center gap-2 text-sm">
                      <input
                        type="radio"
                        name="return_reason"
                        checked={returnReason === r.code}
                        onChange={() => setReturnReason(r.code)}
                      />
                      {r.label}
                    </label>
                  ))}
                </fieldset>
                <label className="block text-sm font-medium">
                  Notes (optional)
                  <textarea
                    className="mt-1 w-full rounded border border-[var(--color-border)] px-3 py-2 text-sm"
                    rows={3}
                    value={returnNotes}
                    onChange={(e) => setReturnNotes(e.target.value)}
                  />
                </label>
              </div>
              <div className="mt-5 flex justify-end gap-2">
                <Button variant="ghost" disabled={busy} onClick={() => setReturnOpen(false)}>
                  Cancel
                </Button>
                <Button
                  disabled={busy}
                  onClick={async () => {
                    setBusy(true);
                    try {
                      await apiSend("post", `/orders/${order.id}/returns`, {
                        items: [
                          {
                            order_item_id: selectedReturnItem.id,
                            quantity: returnQty,
                            reason: returnReason,
                          },
                        ],
                        notes: returnNotes.trim() || undefined,
                      });
                      setReturnOpen(false);
                      await reload();
                      toast("Your return request has been submitted.");
                    } catch (e) {
                      toast(e instanceof Error ? e.message : "Return failed", "error");
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  Submit return
                </Button>
              </div>
            </div>
          </div>
        ) : null}

        {cancelOpen ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-[var(--radius-lg)] bg-white p-5 shadow-lg">
              <h2 className="text-lg font-bold">Cancel this order?</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">{order.order_number}</p>
              <div className="mt-4 space-y-2">
                {CANCEL_REASONS.map((r) => (
                  <label key={r.code} className="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                      type="radio"
                      name="cancel_reason"
                      checked={reasonCode === r.code}
                      onChange={() => setReasonCode(r.code)}
                    />
                    {r.label}
                  </label>
                ))}
                {reasonCode === "other" ? (
                  <input
                    className="mt-2 w-full rounded border border-[var(--color-border)] px-3 py-2 text-sm"
                    placeholder="Tell us more"
                    value={reasonOther}
                    onChange={(e) => setReasonOther(e.target.value)}
                  />
                ) : null}
              </div>
              <div className="mt-5 flex justify-end gap-2">
                <Button variant="ghost" disabled={busy} onClick={() => setCancelOpen(false)}>
                  Keep order
                </Button>
                <Button
                  variant="danger"
                  disabled={busy}
                  onClick={async () => {
                    setBusy(true);
                    try {
                      await apiSend("post", `/orders/${order.id}/cancel`, {
                        reason_code: reasonCode,
                        reason: reasonCode === "other" ? reasonOther : undefined,
                      });
                      setCancelOpen(false);
                      await reload();
                      toast("Order cancelled");
                    } catch (e) {
                      toast(e instanceof Error ? e.message : "Could not cancel", "error");
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  Cancel order
                </Button>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}

function Row({
  label,
  value,
  strong,
}: {
  label: string;
  value: string;
  strong?: boolean;
}) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="text-[var(--color-muted)]">{label}</dt>
      <dd className={strong ? "text-base font-bold text-[var(--color-primary-deep)]" : "font-medium"}>
        {value}
      </dd>
    </div>
  );
}
