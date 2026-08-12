"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiGet } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";

type LoyaltyAccount = {
  balance: number;
  lifetime_earned: number;
  lifetime_redeemed: number;
  status: string;
  points_per_rupee?: number;
  how_it_works?: string[];
};

type LoyaltyTx = {
  id: number;
  type: string;
  points: number;
  balance_after: number;
  reason?: string | null;
  created_at?: string | null;
};

export default function RewardsPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [account, setAccount] = useState<LoyaltyAccount | null>(null);
  const [txs, setTxs] = useState<LoyaltyTx[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [a, h] = await Promise.all([
        apiGet<LoyaltyAccount>("/customer/loyalty"),
        apiGet<LoyaltyTx[]>("/customer/loyalty/transactions", { per_page: 30 }),
      ]);
      setAccount(a.data);
      setTxs(Array.isArray(h.data) ? h.data : []);
    } catch {
      setAccount(null);
      setTxs([]);
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
        title="Rewards"
        description="Sign in to view your reward points."
        actionHref={loginHref("/account/rewards")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-2xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Rewards</h1>
          <p>Points are earned when orders are delivered.</p>
        </div>

        {loading ? (
          <Skeleton className="h-24 w-full" />
        ) : !account ? (
          <EmptyState title="Rewards unavailable" description="Could not load your loyalty account." />
        ) : (
          <>
            <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
              <p className="text-sm uppercase tracking-wide text-[var(--color-muted)]">Current balance</p>
              <p className="mt-1 text-4xl font-semibold tabular-nums text-[var(--color-primary-deep)]">
                {account.balance}
              </p>
              <p className="mt-2 text-sm text-[var(--color-muted)]">
                Lifetime earned {account.lifetime_earned} · Redeemed/reversed {account.lifetime_redeemed}
              </p>
            </div>

            {account.how_it_works?.length ? (
              <div className="mt-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
                <h2 className="font-semibold">How it works</h2>
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-[var(--color-muted)]">
                  {account.how_it_works.map((line) => (
                    <li key={line}>{line}</li>
                  ))}
                </ul>
              </div>
            ) : null}

            <h2 className="mt-8 font-semibold">Points history</h2>
            {!txs.length ? (
              <EmptyState
                title="No points yet"
                description="Points are awarded when an order is delivered."
                actionHref="/account/orders"
                actionLabel="View orders"
              />
            ) : (
              <ul className="mt-3 space-y-2">
                {txs.map((t) => (
                  <li
                    key={t.id}
                    className="flex justify-between gap-3 rounded border border-[var(--color-border)] bg-white px-3 py-2 text-sm"
                  >
                    <div>
                      <p className="font-medium">
                        {t.type} · {t.points > 0 ? `+${t.points}` : t.points}
                      </p>
                      <p className="text-xs text-[var(--color-muted)]">{t.reason || "—"}</p>
                    </div>
                    <div className="text-right text-xs text-[var(--color-muted)]">
                      Balance {t.balance_after}
                      <div>
                        {t.created_at ? new Date(t.created_at).toLocaleDateString("en-IN") : ""}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}

        <div className="mt-6">
          <Link href="/account">
            <Button variant="outline">Back to account</Button>
          </Link>
        </div>
      </div>
    </section>
  );
}
