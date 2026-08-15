"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiGet } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";

type Sub = {
  id: number;
  subscription_number: string;
  status: string;
  product_name?: string;
  product_slug?: string;
  frequency: string;
  quantity: number;
  unit_price: number;
  next_billing_at?: string | null;
  billing_model?: string;
};

export default function SubscriptionsPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [rows, setRows] = useState<Sub[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet<Sub[]>("/subscriptions", { per_page: 30 });
      setRows(Array.isArray(res.data) ? res.data : []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load subscriptions");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (bootstrapped && user) void load();
  }, [bootstrapped, user, load]);

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
        title="Subscriptions"
        description="Sign in to manage recurring plant-care deliveries."
        actionHref={loginHref("/account/subscriptions")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-3xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Subscriptions</h1>
          <p className="text-[var(--color-muted)]">
            Each cycle creates a normal order. Payment is required per cycle — we do not auto-charge cards.
          </p>
        </div>

        {loading ? <Skeleton className="h-32 w-full" /> : null}
        {!loading && rows.length === 0 ? (
          <EmptyState title="No subscriptions yet" description="Subscribe from a product page when a plan is available." />
        ) : null}

        <ul className="space-y-3">
          {rows.map((s) => (
            <li
              key={s.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
            >
              <div>
                <p className="font-semibold text-[var(--color-primary-deep)]">{s.subscription_number}</p>
                <p className="text-sm">
                  {s.product_name} · {s.frequency} · Qty {s.quantity} · {money(s.unit_price)}
                </p>
                <p className="text-xs text-[var(--color-muted)]">
                  {s.status}
                  {s.next_billing_at ? ` · Next ${new Date(s.next_billing_at).toLocaleDateString()}` : ""}
                </p>
              </div>
              <Link href={`/account/subscriptions/${s.id}`}>
                <Button variant="secondary">Manage</Button>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
