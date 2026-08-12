"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";

type SubDetail = {
  id: number;
  subscription_number: string;
  status: string;
  product_name?: string;
  plan_name?: string;
  frequency: string;
  quantity: number;
  unit_price: number;
  next_billing_at?: string | null;
  billing_model?: string;
  actions?: {
    can_pause?: boolean;
    can_resume?: boolean;
    can_cancel?: boolean;
  };
  cycles?: Array<{
    cycle_number: number;
    status: string;
    order_id?: number | null;
    amount: number;
  }>;
};

export default function SubscriptionDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const [row, setRow] = useState<SubDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await apiGet<SubDetail>(`/subscriptions/${id}`);
      setRow(res.data);
    } catch {
      setRow(null);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (bootstrapped && user) void load();
  }, [bootstrapped, user, load]);

  async function act(path: string, body?: Record<string, unknown>) {
    setBusy(true);
    try {
      await apiSend("post", `/subscriptions/${id}/${path}`, body ?? {});
      toast("Updated");
      await load();
    } catch (e) {
      toast(e instanceof Error ? e.message : "Failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-2xl">
          <Skeleton className="h-24 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Subscription"
        description="Sign in to manage this subscription."
        actionHref={loginHref(`/account/subscriptions/${id}`)}
        actionLabel="Sign in"
      />
    );
  }

  if (loading) {
    return (
      <section className="section">
        <div className="container max-w-2xl">
          <Skeleton className="h-40 w-full" />
        </div>
      </section>
    );
  }

  if (!row) {
    return <EmptyState title="Not found" description="This subscription is unavailable." actionHref="/account/subscriptions" actionLabel="Back" />;
  }

  return (
    <section className="section">
      <div className="container max-w-2xl space-y-6">
        <div>
          <Link href="/account/subscriptions" className="text-sm text-[var(--color-muted)]">
            ← Subscriptions
          </Link>
          <h1 className="display mt-2 text-4xl text-[var(--color-primary-deep)]">{row.subscription_number}</h1>
          <p className="text-[var(--color-muted)]">
            {row.product_name} · {row.frequency} · {row.status}
          </p>
        </div>

        <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4 text-sm">
          <p>
            {row.quantity} × {money(row.unit_price)} locked price
          </p>
          <p className="mt-1 text-[var(--color-muted)]">
            Next cycle: {row.next_billing_at ? new Date(row.next_billing_at).toLocaleString() : "—"}
          </p>
          <p className="mt-2 text-xs text-[var(--color-muted)]">
            Billing model: pay per cycle. Existing fulfilled orders are not cancelled if you cancel the subscription.
          </p>
        </div>

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
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => {
                if (window.confirm("Cancel this subscription?")) void act("cancel", { reason: "Customer cancelled" });
              }}
            >
              Cancel
            </Button>
          ) : null}
        </div>

        <div>
          <h2 className="font-semibold">Cycle history</h2>
          <ul className="mt-2 space-y-2 text-sm">
            {(row.cycles ?? []).map((c) => (
              <li key={c.cycle_number} className="flex justify-between border-b border-[var(--color-border)] py-2">
                <span>
                  Cycle #{c.cycle_number} · {c.status}
                  {c.order_id ? (
                    <>
                      {" · "}
                      <Link className="underline" href={`/account/orders/${c.order_id}`}>
                        Order
                      </Link>
                    </>
                  ) : null}
                </span>
                <span>{money(c.amount)}</span>
              </li>
            ))}
          </ul>
        </div>

        <Button variant="outline" onClick={() => router.push("/shop")}>
          Continue shopping
        </Button>
      </div>
    </section>
  );
}
