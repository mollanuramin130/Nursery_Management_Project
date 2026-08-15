"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiGet } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Badge } from "@/components/ui/Badge";
import { Skeleton } from "@/components/ui/Skeleton";
import { Button } from "@/components/ui/Button";

type MyReview = {
  id: number;
  product_id: number;
  product_name?: string | null;
  product_slug?: string | null;
  rating: number;
  title?: string | null;
  body?: string | null;
  status: string;
  created_at?: string | null;
};

export default function MyReviewsPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [rows, setRows] = useState<MyReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet<MyReview[]>("/customer/reviews", { per_page: 30 });
      setRows(Array.isArray(res.data) ? res.data : []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load reviews");
      // QA-40: keep previous rows on refresh failure
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
        title="My reviews"
        description="Sign in to see reviews you have submitted."
        actionHref={loginHref("/account/reviews")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-2xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">My reviews</h1>
          <p>Pending reviews appear on product pages after moderation.</p>
        </div>
        {error && !rows.length ? (
          <div className="rounded-[var(--radius-md)] border border-[var(--color-border)] bg-white p-6 text-center">
            <p className="font-semibold text-[var(--color-primary-deep)]">Couldn’t load reviews</p>
            <p className="mt-2 text-sm text-[var(--color-muted)]">{error}</p>
            <div className="mt-4"><Button onClick={() => void load()}>Try again</Button></div>
          </div>
        ) : loading && !rows.length ? (
          <Skeleton className="h-24 w-full" />
        ) : !rows.length ? (
          <EmptyState
            title="No reviews yet"
            description="After a delivered order, rate products from the order page."
            actionHref="/account/orders"
            actionLabel="View orders"
          />
        ) : (
          <ul className="space-y-3">
            {rows.map((r) => (
              <li
                key={r.id}
                className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <p className="font-semibold">{r.product_name ?? `Product #${r.product_id}`}</p>
                    <p className="text-sm text-[var(--color-muted)]">
                      {r.rating}★ · {r.title || "No title"}
                    </p>
                  </div>
                  <Badge tone={r.status === "approved" ? "success" : "warning"}>{r.status}</Badge>
                </div>
                {r.body ? <p className="mt-2 text-sm text-[var(--color-muted)]">{r.body}</p> : null}
                {r.product_slug ? (
                  <Link
                    href={`/product/${r.product_slug}`}
                    className="mt-2 inline-block text-sm font-medium text-[var(--color-primary)]"
                  >
                    View product
                  </Link>
                ) : null}
              </li>
            ))}
          </ul>
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
