"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useRef, useState } from "react";
import { CheckoutStepper } from "@/components/checkout/CheckoutStepper";
import { Button } from "@/components/ui/Button";
import { CheckoutSkeleton } from "@/components/ui/Skeleton";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { EmptyState } from "@/components/ui/EmptyState";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import {
  nextPreviewGeneration,
  shouldApplyPreviewResult,
} from "@/lib/checkout-preview";
import { localStubPayment, openRazorpayCheckout, checkoutPayableTotal, canPlaceOrder } from "@/lib/razorpay";
import {
  mapPaymentApiStatus,
  normalizeUpiInitiateMode,
  qrImageSrc,
  shouldContinueUpiPoll,
  shouldOpenRazorpayCheckout,
  shouldShowUpiIntent,
  shouldShowUpiQr,
  type UpiClientPayload,
  type UpiInitiateMode,
  type UpiPaymentUiStatus,
  upiStatusLabel,
} from "@/lib/upi-payment";
import { checkoutService, customerService } from "@/lib/services";
import { money } from "@/lib/format";
import type { Address, ShippingMethod } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { cn } from "@/lib/cn";

export default function CheckoutPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const cart = useCartStore((s) => s.cart);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();

  const [step, setStep] = useState(1);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [methods, setMethods] = useState<ShippingMethod[]>([]);
  const [addressId, setAddressId] = useState<number | null>(null);
  const [shippingMethodId, setShippingMethodId] = useState<number | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<"cod" | "upi">("cod");
  /** Preferred initiate mode — backend remains authoritative on the response. */
  const [upiMode, setUpiMode] = useState<UpiInitiateMode>("checkout");
  const [upiUi, setUpiUi] = useState<{
    orderId: number;
    orderNumber: string;
    paymentId: number;
    amount: number;
    payload: UpiClientPayload;
    status: UpiPaymentUiStatus;
  } | null>(null);
  const upiStatusRef = useRef<UpiPaymentUiStatus>("pending");
  useEffect(() => {
    upiStatusRef.current = upiUi?.status ?? "pending";
  }, [upiUi?.status]);
  const [notes, setNotes] = useState("");
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [preview, setPreview] = useState<{
    subtotal?: number;
    shipping_total?: number;
    discount_total?: number;
    tax_total?: number;
    grand_total?: number;
  } | null>(null);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  /** Monotonic generation so slower preview responses cannot overwrite newer ones (QA-PERF-011). */
  const previewGenRef = useRef(0);
  const [busy, setBusy] = useState(false);
  const [addrForm, setAddrForm] = useState({
    name: "",
    phone: "",
    line1: "",
    line2: "",
    city: "",
    state: "",
    postal_code: "",
    label: "Home",
  });

  async function loadCheckout() {
    const [addrRes, shipRes] = await Promise.all([
      customerService.addresses(),
      apiGet<ShippingMethod[]>("/shipping/methods"),
    ]);
    setAddresses(addrRes.data);
    setMethods(shipRes.data);
    const def = addrRes.data.find((a) => a.is_default) ?? addrRes.data[0];
    if (def) setAddressId(def.id);
    else setShowAddressForm(true);
    if (shipRes.data[0]) setShippingMethodId(shipRes.data[0].id);
  }

  useEffect(() => {
    if (!bootstrapped || !user) return;
    void fetchCart();
    let cancelled = false;
    void (async () => {
      try {
        await loadCheckout();
      } catch (e) {
        if (!cancelled) toast(e instanceof Error ? e.message : "Failed to load checkout", "error");
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [bootstrapped, user, fetchCart, toast]);

  async function refreshPreview(): Promise<boolean> {
    if (!user || !addressId || !shippingMethodId) return false;
    const gen = nextPreviewGeneration(previewGenRef.current);
    previewGenRef.current = gen;
    setPreviewLoading(true);
    setPreviewError(null);
    try {
      const res = await checkoutService.preview({
        address_id: addressId,
        shipping_method_id: shippingMethodId,
        coupon_code: cart?.coupon_code ?? undefined,
      });
      if (!shouldApplyPreviewResult(gen, previewGenRef.current)) {
        return false;
      }
      setPreview(res.data);
      setPreviewError(null);
      return res.data?.grand_total != null;
    } catch (err) {
      if (!shouldApplyPreviewResult(gen, previewGenRef.current)) {
        return false;
      }
      setPreview(null);
      setPreviewError(
        err instanceof Error ? err.message : "Unable to calculate checkout totals",
      );
      return false;
    } finally {
      if (shouldApplyPreviewResult(gen, previewGenRef.current)) {
        setPreviewLoading(false);
      }
    }
  }

  const cartLinesKey =
    cart?.items?.map((i) => `${i.id}:${i.quantity}`).join("|") ?? "";

  useEffect(() => {
    // Any cart/checkout input change invalidates prior preview (stale protection).
    setPreview(null);
    void refreshPreview();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- refresh when checkout + cart lines change
  }, [user, addressId, shippingMethodId, cart?.coupon_code, cart?.item_count, cartLinesKey]);

  async function saveAddress(e: FormEvent) {
    e.preventDefault();
    try {
      await customerService.createAddress({
        ...addrForm,
        country: "IN",
        is_default: addresses.length === 0,
      });
      toast("Address saved");
      setShowAddressForm(false);
      await loadCheckout();
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not save address", "error");
    }
  }

  function nextFromAddress() {
    if (!addressId) {
      toast("Select or add a delivery address", "error");
      return;
    }
    setStep(2);
  }

  function nextFromDelivery() {
    if (!shippingMethodId) {
      toast("Select a delivery method", "error");
      return;
    }
    setStep(3);
  }

  async function onPlace(e?: FormEvent) {
    e?.preventDefault();
    if (busy || previewLoading) return;
    if (!addressId || !shippingMethodId) {
      toast("Select address and delivery method", "error");
      setStep(1);
      return;
    }
    if (!(cart?.item_count)) {
      toast("Your cart is empty", "error");
      return;
    }

    // Sync lock before network work (parity with Flutter _submissionLocked).
    setBusy(true);
    const requestId =
      typeof crypto !== "undefined" && "randomUUID" in crypto
        ? `web_${crypto.randomUUID()}`
        : `web_${Date.now()}`;
    try {
      // Force fresh authoritative preview — never place on stale/missing totals.
      const previewOk = await refreshPreview();
      if (!previewOk) {
        toast(
          previewError ??
            "Checkout totals are unavailable. Retry preview before placing the order.",
          "error",
        );
        return;
      }

      const orderRes = await checkoutService.placeOrder(
        {
          address_id: addressId,
          shipping_method_id: shippingMethodId,
          payment_method: paymentMethod,
          coupon_code: cart?.coupon_code ?? undefined,
          notes: notes || undefined,
        },
        requestId,
      );

      if (paymentMethod === "upi") {
        const initiateMode = normalizeUpiInitiateMode(upiMode);
        const pay = await apiSend<{
          payment_id: number;
          provider_order_id: string;
          amount: number;
          currency: string;
          method?: string;
          upi_mode?: string;
          expires_at?: string;
          client_payload: UpiClientPayload;
        }>("post", "/payments/initiate", {
          order_id: orderRes.data.id,
          method: "upi",
          mode: initiateMode,
        });

        const payload = {
          ...pay.data.client_payload,
          upi_mode:
            pay.data.client_payload.upi_mode ??
            pay.data.upi_mode ??
            initiateMode,
        };
        const providerOrderId = pay.data.provider_order_id || payload.order_id;

        // Never mark PAID from client alone — wait UI / verify / poll use the server.
        setUpiUi({
          orderId: orderRes.data.id,
          orderNumber: orderRes.data.order_number,
          paymentId: pay.data.payment_id,
          amount: pay.data.amount,
          payload,
          status: "pending",
        });

        if (shouldOpenRazorpayCheckout(payload)) {
          try {
            const gatewayResult = await openRazorpayCheckout({
              key: payload.key || "",
              amount: payload.amount,
              currency: payload.currency || pay.data.currency || "INR",
              name: payload.name || "GreenLeaf Nursery",
              description: `Order ${orderRes.data.order_number}`,
              order_id: providerOrderId,
              prefill: payload.prefill,
              method: payload.method,
            });
            const verified = await apiSend<{
              payment_status: string;
              order_status: string;
            }>("post", "/payments/verify", {
              payment_id: pay.data.payment_id,
              provider_order_id: gatewayResult.razorpay_order_id,
              provider_payment_id: gatewayResult.razorpay_payment_id,
              provider_signature: gatewayResult.razorpay_signature,
            });
            if (
              verified.data.payment_status !== "success" ||
              verified.data.order_status !== "CONFIRMED"
            ) {
              toast("Payment could not be confirmed. Check your order status.", "error");
              router.push(`/account/orders/${orderRes.data.id}?pay=pending`);
              return;
            }
            await fetchCart();
            toast("Payment successful — order confirmed");
            router.push(
              `/account/orders/${orderRes.data.id}?placed=${encodeURIComponent(orderRes.data.order_number)}&paid=1`,
            );
            return;
          } catch (checkoutErr) {
            const msg =
              checkoutErr instanceof Error ? checkoutErr.message : "Payment cancelled";
            toast(
              msg.includes("cancel")
                ? "Payment cancelled. You can retry from this screen or your order."
                : msg,
              "error",
            );
            // Fall through to wait UI so customer can retry / poll / use QR/intent if present.
          }
        }

        if (shouldShowUpiQr(payload) || shouldShowUpiIntent(payload)) {
          toast("Complete UPI payment. Status updates from the server.");
        } else if ((payload.mode ?? "") === "local_stub") {
          toast("Local test payment mode — confirm only after server verification.");
        } else {
          toast("Complete payment in Razorpay. Status updates from the server.");
        }
        return;
      }

      await fetchCart();
      toast("Order placed successfully");
      router.push(
        `/account/orders/${orderRes.data.id}?placed=${encodeURIComponent(orderRes.data.order_number)}`,
      );
    } catch (err) {
      const message = err instanceof Error ? err.message : "Checkout failed";
      toast(message, "error");
      await fetchCart();
    } finally {
      setBusy(false);
    }
  }

  async function refreshUpiStatus(): Promise<UpiPaymentUiStatus | null> {
    if (!upiUi) return null;
    try {
      const res = await apiGet<{
        payment_status?: string;
        order_status?: string;
      }>(`/payments/${upiUi.paymentId}`);
      const mapped = mapPaymentApiStatus(res.data.payment_status);
      setUpiUi((prev) => (prev ? { ...prev, status: mapped === "pending" ? "polling" : mapped } : prev));
      // QA-36: navigate as confirmed only when server order_status is CONFIRMED.
      if (mapped === "paid" && res.data.order_status === "CONFIRMED") {
        await fetchCart();
        toast("Payment successful — order confirmed");
        router.push(
          `/account/orders/${upiUi.orderId}?placed=${encodeURIComponent(upiUi.orderNumber)}&paid=1`,
        );
      }
      return mapped;
    } catch (e) {
      toast(e instanceof Error ? e.message : "Could not refresh payment status", "error");
      return null;
    }
  }

  async function confirmUpiStubPayment() {
    if (!upiUi) return;
    if (!upiUi.payload.stub_confirm_allowed || upiUi.payload.mode !== "local_stub") {
      toast("Waiting for server payment confirmation…", "error");
      return;
    }
    setBusy(true);
    try {
      const gatewayResult = localStubPayment(upiUi.payload.order_id, upiUi.paymentId);
      const verified = await apiSend<{
        payment_status: string;
        order_status: string;
      }>("post", "/payments/verify", {
        payment_id: upiUi.paymentId,
        provider_order_id: gatewayResult.razorpay_order_id,
        provider_payment_id: gatewayResult.razorpay_payment_id,
        provider_signature: gatewayResult.razorpay_signature,
      });
      if (verified.data.payment_status !== "success" || verified.data.order_status !== "CONFIRMED") {
        setUpiUi((p) => (p ? { ...p, status: "failed" } : p));
        toast("Payment could not be confirmed.", "error");
        return;
      }
      await fetchCart();
      toast("Payment successful — order confirmed");
      router.push(
        `/account/orders/${upiUi.orderId}?placed=${encodeURIComponent(upiUi.orderNumber)}&paid=1`,
      );
    } catch (e) {
      toast(e instanceof Error ? e.message : "Payment confirmation failed", "error");
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    if (!upiUi?.paymentId) return;
    if (upiUi.status === "paid") return;
    const started = Date.now();
    upiStatusRef.current = "polling";
    setUpiUi((p) => (p ? { ...p, status: "polling" } : p));
    const id = window.setInterval(() => {
      if (
        !shouldContinueUpiPoll({
          status: upiStatusRef.current,
          startedAtMs: started,
          nowMs: Date.now(),
        })
      ) {
        window.clearInterval(id);
        setUpiUi((p) =>
          p && (p.status === "polling" || p.status === "pending")
            ? { ...p, status: "expired" }
            : p,
        );
        return;
      }
      void refreshUpiStatus();
    }, 3000);
    return () => window.clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- poll against paymentId only; status via ref
  }, [upiUi?.paymentId]);

  if (upiUi) {
    const img = shouldShowUpiQr(upiUi.payload) ? qrImageSrc(upiUi.payload) : null;
    const intentUrl = shouldShowUpiIntent(upiUi.payload)
      ? (upiUi.payload.upi_intent_url || "").trim()
      : "";
    const canRetryCheckout = shouldOpenRazorpayCheckout(upiUi.payload);
    return (
      <section className="section">
        <div className="mx-auto max-w-lg space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6">
          <h1 className="display text-3xl text-[var(--color-primary-deep)]">UPI / Razorpay</h1>
          <p className="text-sm text-[var(--color-muted)]">
            Order {upiUi.orderNumber} · Amount {money(upiUi.amount)}
          </p>
          <p className="text-sm font-semibold">Status: {upiStatusLabel(upiUi.status)}</p>
          {img ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={img} alt="UPI QR code" width={220} height={220} className="mx-auto rounded-md border" />
          ) : null}
          <p className="text-center text-sm text-[var(--color-muted)]">
            {img
              ? "Scan using any supported UPI app. Confirmation comes from the server — not from this screen alone."
              : "Complete payment securely. Confirmation comes from the server — not from this screen alone."}
          </p>
          <div className="flex flex-col gap-2">
            {canRetryCheckout ? (
              <Button
                type="button"
                fullWidth
                disabled={busy}
                onClick={() => {
                  void (async () => {
                    setBusy(true);
                    try {
                      const gatewayResult = await openRazorpayCheckout({
                        key: upiUi.payload.key || "",
                        amount: upiUi.payload.amount,
                        currency: upiUi.payload.currency || "INR",
                        name: upiUi.payload.name || "GreenLeaf Nursery",
                        description: `Order ${upiUi.orderNumber}`,
                        order_id: upiUi.payload.order_id,
                        prefill: upiUi.payload.prefill,
                        method: upiUi.payload.method,
                      });
                      const verified = await apiSend<{
                        payment_status: string;
                        order_status: string;
                      }>("post", "/payments/verify", {
                        payment_id: upiUi.paymentId,
                        provider_order_id: gatewayResult.razorpay_order_id,
                        provider_payment_id: gatewayResult.razorpay_payment_id,
                        provider_signature: gatewayResult.razorpay_signature,
                      });
                      if (
                        verified.data.payment_status !== "success" ||
                        verified.data.order_status !== "CONFIRMED"
                      ) {
                        toast("Payment could not be confirmed. Check your order status.", "error");
                        return;
                      }
                      await fetchCart();
                      toast("Payment successful — order confirmed");
                      router.push(
                        `/account/orders/${upiUi.orderId}?placed=${encodeURIComponent(upiUi.orderNumber)}&paid=1`,
                      );
                    } catch (e) {
                      toast(e instanceof Error ? e.message : "Payment cancelled", "error");
                    } finally {
                      setBusy(false);
                    }
                  })();
                }}
              >
                {busy ? "Opening Razorpay…" : "Pay with Razorpay"}
              </Button>
            ) : null}
            {intentUrl ? (
              <a
                href={intentUrl}
                className="inline-flex h-11 items-center justify-center rounded-[var(--radius-md)] border border-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-primary)]"
              >
                Open UPI app
              </a>
            ) : null}
            <Button type="button" fullWidth disabled={busy} onClick={() => void refreshUpiStatus()}>
              Check payment status
            </Button>
            {upiUi.payload.stub_confirm_allowed && upiUi.payload.mode === "local_stub" ? (
              <Button type="button" variant="outline" fullWidth disabled={busy} onClick={() => void confirmUpiStubPayment()}>
                Dev: confirm stub payment
              </Button>
            ) : null}
            <Button
              type="button"
              variant="outline"
              fullWidth
              onClick={() => router.push(`/account/orders/${upiUi.orderId}?pay=pending`)}
            >
              View order
            </Button>
          </div>
        </div>
      </section>
    );
  }

  if (!bootstrapped) {
    return (
      <section className="section">
        <CheckoutSkeleton />
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Sign in to checkout"
        description="Your cart stays with you — login to choose address and payment."
        actionHref={loginHref("/checkout")}
        actionLabel="Sign in"
      />
    );
  }

  if (!cart?.item_count) {
    return (
      <EmptyState
        title="Your cart is empty"
        description="Add plants before checking out."
        actionHref="/shop"
        actionLabel="Explore plants"
      />
    );
  }

  const previewReady = preview?.grand_total != null;
  const total = checkoutPayableTotal(preview);
  const canPlace = canPlaceOrder({ previewReady, busy, previewLoading });

  return (
    <section className="section pb-28 md:pb-16">
      <div className="container grid gap-8 lg:grid-cols-[1.35fr_0.85fr]">
        <div>
          <div className="section-head !mb-4">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">Checkout</h1>
            <p>Confirm delivery, shipping, and payment.</p>
          </div>

          <div className="lg:hidden">
            <CheckoutStepper step={step} onStep={(n) => n < step && setStep(n)} />
          </div>

          {/* Desktop: all steps visible */}
          <form onSubmit={onPlace} className="hidden space-y-5 lg:block">
            <AddressBlock
              addresses={addresses}
              addressId={addressId}
              setAddressId={setAddressId}
              showAddressForm={showAddressForm}
              setShowAddressForm={setShowAddressForm}
              addrForm={addrForm}
              setAddrForm={setAddrForm}
              saveAddress={saveAddress}
            />
            <DeliveryBlock
              methods={methods}
              shippingMethodId={shippingMethodId}
              setShippingMethodId={setShippingMethodId}
            />
            <PaymentBlock
              paymentMethod={paymentMethod}
              setPaymentMethod={setPaymentMethod}
              upiMode={upiMode}
              setUpiMode={setUpiMode}
            />
            <Field label="Order notes (optional)">
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Gate code, preferred delivery slot…"
              />
            </Field>
            {previewError ? (
              <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                {previewError}{" "}
                <button type="button" className="font-semibold underline" onClick={() => void refreshPreview()}>
                  Retry
                </button>
              </p>
            ) : null}
            <Button type="submit" fullWidth size="lg" disabled={!canPlace}>
              {busy
                ? "Processing…"
                : previewLoading
                  ? "Calculating totals…"
                  : !previewReady
                    ? "Totals unavailable — retry"
                    : paymentMethod === "upi"
                      ? `Pay ${money(total!)} via UPI / Razorpay`
                      : `Place order — ${money(total!)}`}
            </Button>
          </form>

          {/* Mobile step panels */}
          <div className="space-y-5 lg:hidden">
            {step === 1 ? (
              <div className="space-y-4">
                <AddressBlock
                  addresses={addresses}
                  addressId={addressId}
                  setAddressId={setAddressId}
                  showAddressForm={showAddressForm}
                  setShowAddressForm={setShowAddressForm}
                  addrForm={addrForm}
                  setAddrForm={setAddrForm}
                  saveAddress={saveAddress}
                />
                <Button fullWidth onClick={nextFromAddress}>
                  Continue to delivery
                </Button>
              </div>
            ) : null}
            {step === 2 ? (
              <div className="space-y-4">
                <DeliveryBlock
                  methods={methods}
                  shippingMethodId={shippingMethodId}
                  setShippingMethodId={setShippingMethodId}
                />
                <div className="flex gap-2">
                  <Button variant="outline" fullWidth onClick={() => setStep(1)}>
                    Back
                  </Button>
                  <Button fullWidth onClick={nextFromDelivery}>
                    Continue to payment
                  </Button>
                </div>
              </div>
            ) : null}
            {step === 3 ? (
              <div className="space-y-4">
                <PaymentBlock
                  paymentMethod={paymentMethod}
                  setPaymentMethod={setPaymentMethod}
                  upiMode={upiMode}
                  setUpiMode={setUpiMode}
                />
                <Field label="Order notes (optional)">
                  <Textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Gate code, preferred delivery slot…"
                  />
                </Field>
                <div className="flex gap-2">
                  <Button variant="outline" fullWidth onClick={() => setStep(2)}>
                    Back
                  </Button>
                  <Button fullWidth onClick={() => setStep(4)}>
                    Review order
                  </Button>
                </div>
              </div>
            ) : null}
            {step === 4 ? (
              <div className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
                <h2 className="font-semibold text-lg">Review & pay</h2>
                <p className="text-sm text-[var(--color-muted)]">
                  Address, delivery, and payment look ready. Confirm to place your order.
                </p>
                <div className="rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-soft,#f7faf8)] px-3 py-2 text-sm">
                  <span className="text-[var(--color-muted)]">Payment method · </span>
                  <strong>
                    {paymentMethod === "upi"
                      ? `UPI / Razorpay (${upiMode === "dynamic_qr" ? "Dynamic QR" : upiMode === "upi_intent" ? "UPI Intent" : "Checkout"})`
                      : "Cash on Delivery"}
                  </strong>
                  <button
                    type="button"
                    className="ml-2 font-semibold text-[var(--color-primary)] underline"
                    onClick={() => setStep(3)}
                  >
                    Change
                  </button>
                </div>
                <SummaryBody cart={cart} preview={preview} total={total} previewReady={previewReady} />
                <div className="flex gap-2">
                  <Button variant="outline" fullWidth onClick={() => setStep(3)}>
                    Back
                  </Button>
                  <Button fullWidth disabled={!canPlace} onClick={() => void onPlace()}>
                    {busy
                      ? "Processing…"
                      : !previewReady
                        ? "Totals unavailable — retry"
                        : paymentMethod === "upi"
                          ? `Pay ${money(total!)} via UPI / Razorpay`
                          : `Place COD order — ${money(total!)}`}
                  </Button>
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <aside className="hidden h-fit rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 shadow-[var(--shadow-sm)] lg:block">
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">Order summary</h2>
          <SummaryBody cart={cart} preview={preview} total={total} previewReady={previewReady} />
          <Link href="/cart" className="mt-4 inline-block text-sm font-semibold text-[var(--color-primary)]">
            ← Edit cart
          </Link>
        </aside>

        {/* Mobile sticky pay on review */}
        {step === 4 ? (
          <div className="fixed inset-x-0 bottom-[var(--bottom-nav-h)] z-[35] border-t border-[var(--color-border)] bg-white/95 p-3 backdrop-blur lg:hidden">
            <div className="flex items-center gap-3">
              <div className="flex-1">
                <p className="text-xs text-[var(--color-muted)]">Total</p>
                <p className="font-bold text-[var(--color-primary-deep)]">
                  {previewReady ? money(total!) : "—"}
                </p>
              </div>
              <Button disabled={!canPlace} onClick={() => void onPlace()}>
                {busy
                  ? "Processing…"
                  : !previewReady
                    ? "Retry totals"
                    : paymentMethod === "upi"
                      ? `Pay ${money(total!)} via UPI / Razorpay`
                      : `Place COD order — ${money(total!)}`}
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}

function SummaryBody({
  cart,
  preview,
  total,
  previewReady,
}: {
  cart: NonNullable<ReturnType<typeof useCartStore.getState>["cart"]>;
  preview: {
    subtotal?: number;
    shipping_total?: number;
    discount_total?: number;
    tax_total?: number;
    grand_total?: number;
  } | null;
  total: number | null;
  previewReady: boolean;
}) {
  return (
    <>
      <ul className="mt-4 space-y-3">
        {(cart?.items ?? []).map((item) => (
          <li key={item.id} className="flex justify-between gap-3 text-sm">
            <span className="text-[var(--color-ink-soft)]">
              {item.name} × {item.quantity}
            </span>
            <span className="font-semibold">{money(item.line_total)}</span>
          </li>
        ))}
      </ul>
      <div className="mt-4 space-y-2 border-t border-[var(--color-border)] pt-4 text-sm">
        {!previewReady ? (
          <p className="text-sm text-red-700">
            Order total will appear after checkout preview succeeds. Place order stays disabled until then.
          </p>
        ) : null}
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Subtotal</span>
          <span>{previewReady ? money(preview?.subtotal ?? 0) : "—"}</span>
        </div>
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Shipping</span>
          <span>{previewReady ? money(preview?.shipping_total ?? 0) : "—"}</span>
        </div>
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Discount</span>
          <span>{previewReady ? `-${money(preview?.discount_total ?? 0)}` : "—"}</span>
        </div>
        {previewReady && preview?.tax_total != null ? (
          <div className="flex justify-between text-[var(--color-muted)]">
            <span>Tax</span>
            <span>{money(preview.tax_total)}</span>
          </div>
        ) : null}
        <div className="flex justify-between text-lg font-bold">
          <span>Total</span>
          <span className="text-[var(--color-primary-deep)]">
            {previewReady && total != null ? money(total) : "—"}
          </span>
        </div>
      </div>
    </>
  );
}

function AddressBlock(props: {
  addresses: Address[];
  addressId: number | null;
  setAddressId: (id: number) => void;
  showAddressForm: boolean;
  setShowAddressForm: (v: boolean | ((b: boolean) => boolean)) => void;
  addrForm: {
    name: string;
    phone: string;
    line1: string;
    line2: string;
    city: string;
    state: string;
    postal_code: string;
    label: string;
  };
  setAddrForm: (v: typeof props.addrForm) => void;
  saveAddress: (e: FormEvent) => void;
}) {
  const {
    addresses,
    addressId,
    setAddressId,
    showAddressForm,
    setShowAddressForm,
    addrForm,
    setAddrForm,
    saveAddress,
  } = props;
  return (
    <section className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="font-semibold text-lg">Delivery address</h2>
        <button
          type="button"
          className="text-sm font-semibold text-[var(--color-primary)]"
          onClick={() => setShowAddressForm((v) => !v)}
        >
          {showAddressForm ? "Cancel" : "+ Add address"}
        </button>
      </div>
      {addresses.length ? (
        <div className="space-y-2">
          {addresses.map((a) => (
            <label
              key={a.id}
              className={cn(
                "flex cursor-pointer gap-3 rounded-[var(--radius-md)] border p-3",
                addressId === a.id
                  ? "border-[var(--color-primary)] bg-[var(--color-primary-soft)]"
                  : "border-[var(--color-border)]",
              )}
            >
              <input
                type="radio"
                name="address"
                checked={addressId === a.id}
                onChange={() => setAddressId(a.id)}
              />
              <span className="text-sm">
                <strong>{a.label ?? "Address"}</strong> — {a.name}
                <br />
                {a.line1}, {a.city}, {a.state} - {a.postal_code}
              </span>
            </label>
          ))}
        </div>
      ) : (
        <p className="text-sm text-[var(--color-muted)]">No saved addresses yet.</p>
      )}
      {showAddressForm ? (
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <Field label="Full name">
            <Input
              required
              value={addrForm.name}
              onChange={(e) => setAddrForm({ ...addrForm, name: e.target.value })}
            />
          </Field>
          <Field label="Phone">
            <Input
              required
              value={addrForm.phone}
              onChange={(e) => setAddrForm({ ...addrForm, phone: e.target.value })}
            />
          </Field>
          <Field label="Address line" className="sm:col-span-2">
            <Input
              required
              value={addrForm.line1}
              onChange={(e) => setAddrForm({ ...addrForm, line1: e.target.value })}
            />
          </Field>
          <Field label="City">
            <Input
              required
              value={addrForm.city}
              onChange={(e) => setAddrForm({ ...addrForm, city: e.target.value })}
            />
          </Field>
          <Field label="State">
            <Input
              required
              value={addrForm.state}
              onChange={(e) => setAddrForm({ ...addrForm, state: e.target.value })}
            />
          </Field>
          <Field label="Postal code">
            <Input
              required
              value={addrForm.postal_code}
              onChange={(e) => setAddrForm({ ...addrForm, postal_code: e.target.value })}
            />
          </Field>
          <Field label="Label">
            <Select
              value={addrForm.label}
              onChange={(e) => setAddrForm({ ...addrForm, label: e.target.value })}
            >
              <option>Home</option>
              <option>Office</option>
              <option>Other</option>
            </Select>
          </Field>
          <div className="sm:col-span-2">
            <Button type="button" onClick={saveAddress}>
              Save address
            </Button>
          </div>
        </div>
      ) : null}
    </section>
  );
}

function DeliveryBlock({
  methods,
  shippingMethodId,
  setShippingMethodId,
}: {
  methods: ShippingMethod[];
  shippingMethodId: number | null;
  setShippingMethodId: (id: number) => void;
}) {
  return (
    <section className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
      <h2 className="mb-4 font-semibold text-lg">Delivery method</h2>
      <div className="space-y-2">
        {methods.map((m) => (
          <label
            key={m.id}
            className={cn(
              "flex cursor-pointer items-center justify-between gap-3 rounded-[var(--radius-md)] border p-3",
              shippingMethodId === m.id
                ? "border-[var(--color-primary)] bg-[var(--color-primary-soft)]"
                : "border-[var(--color-border)]",
            )}
          >
            <span className="flex items-center gap-3 text-sm">
              <input
                type="radio"
                name="ship"
                checked={shippingMethodId === m.id}
                onChange={() => setShippingMethodId(m.id)}
              />
              <strong>{m.name}</strong>
            </span>
            <span className="font-semibold">{m.price === 0 ? "FREE" : money(m.price)}</span>
          </label>
        ))}
      </div>
    </section>
  );
}

function PaymentBlock({
  paymentMethod,
  setPaymentMethod,
  upiMode,
  setUpiMode,
}: {
  paymentMethod: "cod" | "upi";
  setPaymentMethod: (v: "cod" | "upi") => void;
  upiMode: UpiInitiateMode;
  setUpiMode: (v: UpiInitiateMode) => void;
}) {
  return (
    <section className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
      <h2 className="mb-4 font-semibold text-lg">Payment Method</h2>
      <div className="space-y-2">
        {[
          {
            id: "cod" as const,
            label: "Cash on Delivery",
            hint: "Pay when your plants arrive",
          },
          {
            id: "upi" as const,
            label: "UPI / Razorpay",
            hint: "Pay securely using UPI",
          },
        ].map((opt) => (
          <label
            key={opt.id}
            className={cn(
              "flex cursor-pointer gap-3 rounded-[var(--radius-md)] border p-3 text-sm",
              paymentMethod === opt.id
                ? "border-[var(--color-primary)] bg-[var(--color-primary-soft)]"
                : "border-[var(--color-border)]",
            )}
          >
            <input
              type="radio"
              name="pay"
              checked={paymentMethod === opt.id}
              onChange={() => setPaymentMethod(opt.id)}
            />
            <span>
              <strong>{opt.label}</strong>
              <br />
              <span className="text-[var(--color-muted)]">{opt.hint}</span>
            </span>
          </label>
        ))}
      </div>
      {paymentMethod === "upi" ? (
        <div className="mt-4 space-y-2 border-t border-[var(--color-border)] pt-4">
          <p className="text-sm font-semibold text-[var(--color-ink)]">UPI option</p>
          <p className="text-xs text-[var(--color-muted)]">
            Uses the existing payment API. The server response decides what you can complete.
          </p>
          {(
            [
              {
                id: "checkout" as const,
                label: "Razorpay Checkout",
                hint: "Open Razorpay TEST / UPI checkout",
              },
              {
                id: "dynamic_qr" as const,
                label: "Dynamic QR",
                hint: "Scan a QR with any UPI app",
              },
              {
                id: "upi_intent" as const,
                label: "UPI Intent",
                hint: "Open your UPI app directly when supported",
              },
            ] as const
          ).map((opt) => (
            <label
              key={opt.id}
              className={cn(
                "flex cursor-pointer gap-3 rounded-[var(--radius-md)] border p-3 text-sm",
                upiMode === opt.id
                  ? "border-[var(--color-primary)] bg-[var(--color-primary-soft)]"
                  : "border-[var(--color-border)]",
              )}
            >
              <input
                type="radio"
                name="upi-mode"
                checked={upiMode === opt.id}
                onChange={() => setUpiMode(opt.id)}
              />
              <span>
                <strong>{opt.label}</strong>
                <br />
                <span className="text-[var(--color-muted)]">{opt.hint}</span>
              </span>
            </label>
          ))}
        </div>
      ) : null}
      <p className="mt-3 text-xs text-[var(--color-muted)]">
        Payment confirmation is always verified by the server — the browser never marks an order paid on its own.
      </p>
    </section>
  );
}
