"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SafeImage } from "@/components/ui/SafeImage";
import { Skeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { useWishlistStore } from "@/store/wishlist";

export default function WishlistPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const items = useWishlistStore((s) => s.items);
  const loading = useWishlistStore((s) => s.loading);
  const error = useWishlistStore((s) => s.error);
  const fetchWishlist = useWishlistStore((s) => s.fetchWishlist);
  const remove = useWishlistStore((s) => s.remove);
  const moveToCart = useWishlistStore((s) => s.moveToCart);
  const toast = useToastStore((s) => s.push);
  const [busyIds, setBusyIds] = useState<Set<number>>(new Set());

  useEffect(() => {
    if (bootstrapped && user) void fetchWishlist();
  }, [bootstrapped, user, fetchWishlist]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container text-[var(--color-muted)]">Loading…</div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Save plants you love"
        description="Sign in to keep a wishlist across devices."
        actionHref={loginHref("/wishlist")}
        actionLabel="Sign in"
      />
    );
  }

  // QA-36-004: fetch error ≠ empty wishlist.
  if (!loading && error && !items.length) {
    return (
      <section className="section">
        <div className="container mx-auto max-w-md px-4 py-16 text-center">
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">
            Couldn’t load wishlist
          </h2>
          <p className="mt-3 text-[var(--color-muted)] leading-relaxed">{error}</p>
          <div className="mt-6">
            <Button onClick={() => void fetchWishlist()}>Try again</Button>
          </div>
        </div>
      </section>
    );
  }

  if (loading && !items.length) {
    return (
      <section className="section">
        <div className="container">
          <div className="section-head">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">Wishlist</h1>
            <p>Saved for later — move them into your cart when ready.</p>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
          </div>
        </div>
      </section>
    );
  }

  if (!loading && !error && !items.length) {
    return (
      <EmptyState
        title="Your wishlist is empty."
        description="Save plants now and come back when you’re ready to buy."
        actionHref="/shop"
        actionLabel="Explore plants"
      />
    );
  }

  return (
    <section className="section">
      <div className="container">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Wishlist</h1>
          <p>Saved for later — move them into your cart when ready.</p>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          {items.map((item) => {
            const p = item.product;
            return (
              <div
                key={item.wishlist_item_id ?? item.id ?? p.id}
                className="flex gap-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
              >
                <div className="relative h-24 w-24 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                  <SafeImage src={p.thumbnail_url} alt="" fill className="object-cover" sizes="96px" />
                </div>
                <div className="min-w-0 flex-1">
                  <Link href={`/product/${p.slug}`} className="font-semibold hover:text-[var(--color-primary)]">
                    {p.name}
                  </Link>
                  <p className="mt-1 font-bold text-[var(--color-primary-deep)]">
                    {money(p.price, p.currency)}
                  </p>
                  {p.stock_status === "out_of_stock" ? (
                    <p className="mt-1 text-sm font-medium text-[var(--color-error,#b91c1c)]">
                      Currently unavailable
                    </p>
                  ) : p.stock_status === "low_stock" ? (
                    <p className="mt-1 text-sm text-[var(--color-warning,#b45309)]">Low stock</p>
                  ) : (
                    <p className="mt-1 text-sm text-[var(--color-muted)]">In stock</p>
                  )}
                  <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      disabled={p.stock_status === "out_of_stock" || busyIds.has(p.id)}
                      onClick={async () => {
                        if (busyIds.has(p.id)) return;
                        setBusyIds((prev) => new Set(prev).add(p.id));
                        try {
                          const cart = await moveToCart(p.id);
                          useCartStore.setState({ cart });
                          toast("Moved to cart");
                        } catch (e) {
                          toast(e instanceof Error ? e.message : "Failed", "error");
                        } finally {
                          setBusyIds((prev) => {
                            const next = new Set(prev);
                            next.delete(p.id);
                            return next;
                          });
                        }
                      }}
                    >
                      {p.stock_status === "out_of_stock"
                        ? "Unavailable"
                        : busyIds.has(p.id)
                          ? "…"
                          : "Move to cart"}
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busyIds.has(p.id)}
                      onClick={async () => {
                        if (busyIds.has(p.id)) return;
                        setBusyIds((prev) => new Set(prev).add(p.id));
                        try {
                          await remove(p.id);
                          toast("Removed from wishlist", "info");
                        } catch (e) {
                          toast(e instanceof Error ? e.message : "Remove failed", "error");
                        } finally {
                          setBusyIds((prev) => {
                            const next = new Set(prev);
                            next.delete(p.id);
                            return next;
                          });
                        }
                      }}
                    >
                      Remove
                    </Button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
