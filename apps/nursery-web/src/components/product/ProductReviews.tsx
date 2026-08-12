"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { ProductRating } from "@/components/product/ProductRating";
import { Button } from "@/components/ui/Button";
import { Field, Input, Textarea } from "@/components/ui/Input";
import { Skeleton } from "@/components/ui/Skeleton";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type Review = {
  id: number;
  rating: number;
  title?: string | null;
  body?: string | null;
  user_name: string;
  verified_purchase?: boolean;
  created_at?: string | null;
  images?: string[];
};

type Summary = {
  rating_avg?: number;
  rating_count?: number;
  distribution?: Record<string, number>;
};

export function ProductReviews({
  productId,
  productSlug,
}: {
  productId: number;
  productSlug: string;
}) {
  const user = useAuthStore((s) => s.user);
  const toast = useToastStore((s) => s.push);
  const searchParams = useSearchParams();
  const [reviews, setReviews] = useState<Review[]>([]);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(searchParams.get("review") === "1");
  const [submitting, setSubmitting] = useState(false);
  const [rating, setRating] = useState(5);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [eligible, setEligible] = useState<boolean | null>(null);
  const [eligibilityReason, setEligibilityReason] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiGet<Review[]>(`/products/${productId}/reviews`, {
        per_page: 12,
        sort: "newest",
      });
      setReviews(Array.isArray(res.data) ? res.data : []);
      const meta = res.meta as { summary?: Summary } | undefined;
      setSummary(meta?.summary ?? null);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not load reviews");
    } finally {
      setLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!user) {
      setEligible(null);
      setEligibilityReason(null);
      return;
    }
    void apiGet<{ eligible: boolean; already_reviewed: boolean; reason: string | null }>(
      `/products/${productId}/review-eligibility`,
    )
      .then((res) => {
        setEligible(res.data.eligible);
        setEligibilityReason(res.data.reason);
      })
      .catch(() => {
        setEligible(null);
        setEligibilityReason(null);
      });
  }, [user, productId]);

  useEffect(() => {
    if (searchParams.get("review") === "1" && eligible) {
      setShowForm(true);
    }
  }, [searchParams, eligible]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!user) return;
    setSubmitting(true);
    try {
      await apiSend("post", `/products/${productId}/reviews`, {
        rating,
        title: title.trim() || undefined,
        body: body.trim() || undefined,
      });
      toast("Thanks! Your review is pending moderation.");
      setShowForm(false);
      setTitle("");
      setBody("");
      setRating(5);
      setEligible(false);
      setEligibilityReason("You have already reviewed this product");
      await load();
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not submit review", "error");
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (error) {
    return <p className="text-sm text-[var(--color-muted)]">{error}</p>;
  }

  const avg = summary?.rating_avg ?? 0;
  const count = summary?.rating_count ?? reviews.length;
  const dist = summary?.distribution;
  const distTotal = dist
    ? Object.values(dist).reduce((a, b) => a + b, 0) || count || 1
    : 0;

  return (
    <div>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">Customer reviews</h2>
          <div className="mt-2">
            <ProductRating avg={avg} count={count} />
          </div>
        </div>
        {user ? (
          eligible ? (
            <Button variant="outline" onClick={() => setShowForm((v) => !v)}>
              {showForm ? "Cancel" : "Write a review"}
            </Button>
          ) : eligible === false ? (
            <p className="text-sm text-[var(--color-muted)]">{eligibilityReason}</p>
          ) : null
        ) : (
          <Link
            href={loginHref(`/product/${productSlug}`)}
            className="inline-flex min-h-11 items-center rounded-full border border-[var(--color-border)] px-4 text-sm font-semibold text-[var(--color-primary-deep)]"
          >
            Sign in to review
          </Link>
        )}
      </div>

      {dist && distTotal > 0 ? (
        <div className="mt-4 max-w-sm space-y-1.5">
          {[5, 4, 3, 2, 1].map((star) => {
            const n = dist[String(star)] ?? 0;
            const pct = Math.round((n / distTotal) * 100);
            return (
              <div key={star} className="flex items-center gap-2 text-xs">
                <span className="w-6">{star}★</span>
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--color-surface-muted)]">
                  <div
                    className="h-full bg-[var(--color-primary)]"
                    style={{ width: `${pct}%` }}
                  />
                </div>
                <span className="w-8 text-right text-[var(--color-muted)]">{n}</span>
              </div>
            );
          })}
        </div>
      ) : null}

      {showForm && user && eligible ? (
        <form
          onSubmit={onSubmit}
          className="mt-6 space-y-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5"
        >
          <p className="text-sm text-[var(--color-muted)]">
            Reviews appear after moderation. Verified purchase is checked by the server.
          </p>
          <Field label="Rating">
            <div className="flex flex-wrap gap-2" role="group" aria-label="Star rating">
              {[5, 4, 3, 2, 1].map((n) => (
                <button
                  key={n}
                  type="button"
                  onClick={() => setRating(n)}
                  className={`min-h-10 rounded-full px-3 text-sm font-semibold ${
                    rating === n
                      ? "bg-[var(--color-primary-deep)] text-white"
                      : "border border-[var(--color-border)]"
                  }`}
                  aria-pressed={rating === n}
                >
                  {n}★
                </button>
              ))}
            </div>
          </Field>
          <Field label="Title (optional)">
            <Input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={120} />
          </Field>
          <Field label="Your experience (optional)">
            <Textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={4}
              maxLength={2000}
            />
          </Field>
          <Button type="submit" disabled={submitting}>
            {submitting ? "Submitting…" : "Submit review"}
          </Button>
        </form>
      ) : null}

      {!reviews.length ? (
        <p className="mt-6 text-[var(--color-muted)]">No reviews yet.</p>
      ) : (
        <ul className="mt-6 space-y-4">
          {reviews.map((r) => (
            <li
              key={r.id}
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-semibold">{r.user_name}</p>
                <ProductRating avg={r.rating} />
              </div>
              {r.verified_purchase ? (
                <p className="mt-1 text-xs font-semibold text-[var(--color-primary-deep)]">
                  Verified purchase
                </p>
              ) : null}
              {r.title ? <p className="mt-2 font-medium">{r.title}</p> : null}
              {r.body ? (
                <p className="mt-1 text-sm leading-relaxed text-[var(--color-ink-soft)]">{r.body}</p>
              ) : null}
              {r.images?.length ? (
                <div className="mt-3 flex flex-wrap gap-2">
                  {r.images.slice(0, 4).map((url) => (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      key={url}
                      src={url}
                      alt=""
                      className="h-16 w-16 rounded-md object-cover"
                    />
                  ))}
                </div>
              ) : null}
              {r.created_at ? (
                <p className="mt-2 text-xs text-[var(--color-muted)]">
                  {new Date(r.created_at).toLocaleDateString("en-IN", {
                    day: "numeric",
                    month: "short",
                    year: "numeric",
                  })}
                </p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
