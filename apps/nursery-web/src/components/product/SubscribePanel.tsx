"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type Plan = {
  id: number;
  name: string;
  frequency: string;
  quantity_default: number;
  unit_price: number;
  currency: string;
  billing_model?: string;
};

type Address = {
  id: number;
  label?: string;
  line1: string;
  city: string;
  is_default?: boolean;
};

export function SubscribePanel({
  productId,
  productSlug,
  oneTimePrice,
  plans,
  outOfStock,
}: {
  productId: number;
  productSlug: string;
  oneTimePrice: number;
  plans: Plan[];
  outOfStock?: boolean;
}) {
  const user = useAuthStore((s) => s.user);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const [planId, setPlanId] = useState(plans[0]?.id ?? 0);
  const [qty, setQty] = useState(plans[0]?.quantity_default ?? 1);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [addressId, setAddressId] = useState<number | "">("");
  const [paymentMethod, setPaymentMethod] = useState<"razorpay" | "cod">("razorpay");
  const [busy, setBusy] = useState(false);

  const plan = plans.find((p) => p.id === planId) ?? plans[0];

  useEffect(() => {
    if (!user) return;
    void (async () => {
      try {
        const res = await apiGet<Address[]>("/customer/addresses");
        const list = Array.isArray(res.data) ? res.data : [];
        setAddresses(list);
        const def = list.find((a) => a.is_default) ?? list[0];
        if (def) setAddressId(def.id);
      } catch {
        setAddresses([]);
      }
    })();
  }, [user]);

  if (!plans.length) return null;

  async function subscribe() {
    if (!user) {
      router.push(loginHref(`/product/${productSlug}`));
      return;
    }
    if (!addressId || !plan) {
      toast("Choose a delivery address", "error");
      return;
    }
    setBusy(true);
    try {
      const res = await apiSend<{
        subscription: { id: number };
        order: { id: number; status: string };
        payment_required?: boolean;
        billing_note?: string;
      }>("post", "/subscriptions", {
        plan_id: plan.id,
        quantity: qty,
        address_id: addressId,
        payment_method: paymentMethod,
      });
      toast(res.message || "Subscription created");
      if (res.data.payment_required && res.data.order?.id) {
        router.push(`/account/orders/${res.data.order.id}`);
      } else {
        router.push(`/account/subscriptions/${res.data.subscription.id}`);
      }
    } catch (e) {
      toast(e instanceof Error ? e.message : "Failed", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-6 space-y-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <div>
        <p className="text-xs font-bold uppercase tracking-wide text-[var(--color-muted)]">Subscribe &amp; save</p>
        <p className="mt-1 text-sm text-[var(--color-muted)]">
          One-time {money(oneTimePrice)} · Subscription from {money(plan?.unit_price ?? oneTimePrice)}. Each cycle creates a normal order you pay for — cards are not auto-charged.
        </p>
      </div>
      <label className="block text-sm font-medium">
        Plan
        <select
          className="mt-1 w-full rounded-[var(--radius-md)] border border-[var(--color-border)] bg-white px-3 py-2"
          value={planId}
          onChange={(e) => {
            const id = Number(e.target.value);
            setPlanId(id);
            const p = plans.find((x) => x.id === id);
            if (p) setQty(p.quantity_default);
          }}
        >
          {plans.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name} · {p.frequency} · {money(p.unit_price)}
            </option>
          ))}
        </select>
      </label>
      <div className="flex flex-wrap gap-3">
        <label className="text-sm font-medium">
          Qty
          <Input
            type="number"
            min={1}
            value={qty}
            onChange={(e) => setQty(Math.max(1, Number(e.target.value) || 1))}
            className="mt-1 !w-24"
          />
        </label>
        <label className="min-w-[12rem] flex-1 text-sm font-medium">
          Address
          <select
            className="mt-1 w-full rounded-[var(--radius-md)] border border-[var(--color-border)] bg-white px-3 py-2"
            value={addressId}
            onChange={(e) => setAddressId(Number(e.target.value))}
            disabled={!user}
          >
            {!user ? <option value="">Sign in to choose</option> : null}
            {addresses.map((a) => (
              <option key={a.id} value={a.id}>
                {a.label || a.line1} · {a.city}
              </option>
            ))}
          </select>
        </label>
      </div>
      <div className="flex flex-wrap gap-3 text-sm">
        <label className="flex items-center gap-2">
          <input
            type="radio"
            checked={paymentMethod === "razorpay"}
            onChange={() => setPaymentMethod("razorpay")}
          />
          Pay online each cycle
        </label>
        <label className="flex items-center gap-2">
          <input type="radio" checked={paymentMethod === "cod"} onChange={() => setPaymentMethod("cod")} />
          COD each cycle
        </label>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button onClick={() => void subscribe()} disabled={busy || outOfStock}>
          {outOfStock ? "Out of stock" : busy ? "Starting…" : "Start subscription"}
        </Button>
        <Link href="/account/subscriptions" className="text-sm text-[var(--color-primary-deep)] underline-offset-2 hover:underline">
          My subscriptions
        </Link>
      </div>
    </div>
  );
}
