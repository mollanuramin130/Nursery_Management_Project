"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { CheckoutStepper } from "@/components/checkout/CheckoutStepper";
import { Button } from "@/components/ui/Button";
import { CheckoutSkeleton } from "@/components/ui/Skeleton";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { EmptyState } from "@/components/ui/EmptyState";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { localStubPayment, openRazorpayCheckout } from "@/lib/razorpay";
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
  const [paymentMethod, setPaymentMethod] = useState<"cod" | "razorpay">("cod");
  const [notes, setNotes] = useState("");
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [preview, setPreview] = useState<{
    subtotal?: number;
    shipping_total?: number;
    discount_total?: number;
    tax_total?: number;
    grand_total?: number;
  } | null>(null);
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

  useEffect(() => {
    if (!user || !addressId || !shippingMethodId) return;
    void checkoutService
      .preview({
        address_id: addressId,
        shipping_method_id: shippingMethodId,
        coupon_code: cart?.coupon_code ?? undefined,
      })
      .then((res) => setPreview(res.data))
      .catch(() => setPreview(null));
  }, [user, addressId, shippingMethodId, cart?.coupon_code]);

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
    if (busy) return;
    if (!addressId || !shippingMethodId) {
      toast("Select address and delivery method", "error");
      setStep(1);
      return;
    }
    if (!(cart?.item_count)) {
      toast("Your cart is empty", "error");
      return;
    }
    setBusy(true);
    const requestId =
      typeof crypto !== "undefined" && "randomUUID" in crypto
        ? `web_${crypto.randomUUID()}`
        : `web_${Date.now()}`;
    try {
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

      if (paymentMethod === "razorpay") {
        const pay = await apiSend<{
          payment_id: number;
          provider_order_id: string;
          amount: number;
          currency: string;
          client_payload: {
            key: string;
            order_id: string;
            amount: number;
            currency: string;
            name?: string;
            mode?: string;
            prefill?: { name?: string; email?: string; contact?: string };
          };
        }>("post", "/payments/initiate", { order_id: orderRes.data.id, method: "razorpay" });

        const payload = pay.data.client_payload;
        const providerOrderId = pay.data.provider_order_id || payload.order_id;

        let gatewayResult;
        if (payload.mode === "local_stub") {
          // Dev only — server accepts local_* when Razorpay secret is empty.
          gatewayResult = localStubPayment(providerOrderId, pay.data.payment_id);
        } else {
          gatewayResult = await openRazorpayCheckout({
            key: payload.key,
            amount: payload.amount,
            currency: payload.currency || pay.data.currency || "INR",
            name: payload.name || "GreenLeaf Nursery",
            description: `Order ${orderRes.data.order_number}`,
            order_id: providerOrderId,
            prefill: payload.prefill,
          });
        }

        const verified = await apiSend<{
          payment_status: string;
          order_status: string;
          order?: { id: number; order_number: string; status: string };
        }>("post", "/payments/verify", {
          payment_id: pay.data.payment_id,
          provider_order_id: gatewayResult.razorpay_order_id,
          provider_payment_id: gatewayResult.razorpay_payment_id,
          provider_signature: gatewayResult.razorpay_signature,
        });

        if (verified.data.payment_status !== "success" || verified.data.order_status !== "CONFIRMED") {
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

  const total = preview?.grand_total ?? cart?.grand_total ?? 0;

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
            <PaymentBlock paymentMethod={paymentMethod} setPaymentMethod={setPaymentMethod} />
            <Field label="Order notes (optional)">
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Gate code, preferred delivery slot…"
              />
            </Field>
            <Button type="submit" fullWidth size="lg" disabled={busy}>
              {busy
                ? "Processing…"
                : paymentMethod === "razorpay"
                  ? `Pay ${money(total)} securely`
                  : `Place order — ${money(total)}`}
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
                <PaymentBlock paymentMethod={paymentMethod} setPaymentMethod={setPaymentMethod} />
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
                <SummaryBody cart={cart} preview={preview} total={total} />
                <div className="flex gap-2">
                  <Button variant="outline" fullWidth onClick={() => setStep(3)}>
                    Back
                  </Button>
                  <Button fullWidth disabled={busy} onClick={() => void onPlace()}>
                    {busy
                      ? "Processing…"
                      : paymentMethod === "razorpay"
                        ? `Pay ${money(total)} securely`
                        : `Place COD order — ${money(total)}`}
                  </Button>
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <aside className="hidden h-fit rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 shadow-[var(--shadow-sm)] lg:block">
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">Order summary</h2>
          <SummaryBody cart={cart} preview={preview} total={total} />
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
                <p className="font-bold text-[var(--color-primary-deep)]">{money(total)}</p>
              </div>
              <Button disabled={busy} onClick={() => void onPlace()}>
                {busy
                  ? "Processing…"
                  : paymentMethod === "razorpay"
                    ? `Pay ${money(total)} securely`
                    : `Place COD order — ${money(total)}`}
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
}: {
  cart: NonNullable<ReturnType<typeof useCartStore.getState>["cart"]>;
  preview: {
    subtotal?: number;
    shipping_total?: number;
    discount_total?: number;
    tax_total?: number;
    grand_total?: number;
  } | null;
  total: number;
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
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Subtotal</span>
          <span>{money(preview?.subtotal ?? cart?.subtotal ?? 0)}</span>
        </div>
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Shipping</span>
          <span>{money(preview?.shipping_total ?? 0)}</span>
        </div>
        <div className="flex justify-between text-[var(--color-muted)]">
          <span>Discount</span>
          <span>-{money(preview?.discount_total ?? cart?.discount_total ?? 0)}</span>
        </div>
        {preview?.tax_total != null ? (
          <div className="flex justify-between text-[var(--color-muted)]">
            <span>Tax</span>
            <span>{money(preview.tax_total)}</span>
          </div>
        ) : null}
        <div className="flex justify-between text-lg font-bold">
          <span>Total</span>
          <span className="text-[var(--color-primary-deep)]">{money(total)}</span>
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
}: {
  paymentMethod: "cod" | "razorpay";
  setPaymentMethod: (v: "cod" | "razorpay") => void;
}) {
  return (
    <section className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
      <h2 className="mb-4 font-semibold text-lg">Payment</h2>
      <div className="space-y-2">
        {[
          { id: "cod" as const, label: "Cash on delivery", hint: "Pay when your plants arrive" },
          { id: "razorpay" as const, label: "Online payment", hint: "UPI / cards / net banking" },
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
      <p className="mt-3 text-xs text-[var(--color-muted)]">
        Payments are processed securely. Provider details stay on the server.
      </p>
    </section>
  );
}
