"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SafeImage } from "@/components/ui/SafeImage";
import { EmptyState } from "@/components/ui/EmptyState";
import { CartSkeleton } from "@/components/ui/Skeleton";
import { Input } from "@/components/ui/Input";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { useWishlistStore } from "@/store/wishlist";
import { useRouter } from "next/navigation";

export default function CartPage() {
  const cart = useCartStore((s) => s.cart);
  const loading = useCartStore((s) => s.loading);
  const mutating = useCartStore((s) => s.mutating);
  const error = useCartStore((s) => s.error);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const updateItem = useCartStore((s) => s.updateItem);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const moveToWishlist = useCartStore((s) => s.moveToWishlist);
  const applyCoupon = useCartStore((s) => s.applyCoupon);
  const removeCoupon = useCartStore((s) => s.removeCoupon);
  const user = useAuthStore((s) => s.user);
  const fetchWishlist = useWishlistStore((s) => s.fetchWishlist);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const [code, setCode] = useState("");

  useEffect(() => {
    void fetchCart();
  }, [fetchCart]);

  async function onCoupon(e: FormEvent) {
    e.preventDefault();
    try {
      await applyCoupon(code.trim());
      toast("Coupon applied");
      setCode("");
    } catch (err) {
      toast(err instanceof Error ? err.message : "Coupon could not be applied", "error");
    }
  }

  if (loading && !cart) {
    return (
      <section className="section">
        <CartSkeleton />
      </section>
    );
  }

  // QA-37-004: fetch error ≠ empty cart.
  if (!loading && error && !cart) {
    return (
      <section className="section">
        <div className="container mx-auto max-w-md px-4 py-16 text-center">
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">
            Couldn’t load cart
          </h2>
          <p className="mt-3 text-[var(--color-muted)] leading-relaxed">{error}</p>
          <div className="mt-6">
            <Button onClick={() => void fetchCart()}>Try again</Button>
          </div>
        </div>
      </section>
    );
  }

  const items = cart?.items ?? [];
  const free = cart?.free_delivery;
  const threshold = free?.threshold ?? 999;
  const remaining = free?.remaining ?? Math.max(0, threshold - (cart?.subtotal ?? 0));
  const qualifies = free?.qualifies ?? remaining <= 0;
  const warnings = cart?.warnings ?? [];
  const checkoutBlocked = Boolean(cart?.checkout_blocked);

  if (!items.length) {
    return (
      <EmptyState
        title="Your cart is waiting for something green"
        description="Browse indoor plants, starter kits, and seasonal picks."
        actionHref="/shop"
        actionLabel="Explore plants"
      />
    );
  }

  return (
    <section className="section">
      <div className="container grid gap-8 lg:grid-cols-[1.4fr_0.8fr]">
        <div>
          <div className="section-head flex flex-wrap items-end justify-between gap-3">
            <div>
              <h1 className="display text-4xl text-[var(--color-primary-deep)]">Your cart</h1>
              <p>{cart?.item_count} items ready for checkout.</p>
            </div>
            <button
              type="button"
              className="text-sm font-semibold text-[var(--color-error)]"
              onClick={() => {
                if (!window.confirm("Clear all items from your cart?")) return;
                void clearCart()
                  .then(() => toast("Cart cleared", "info"))
                  .catch((e) => toast(e instanceof Error ? e.message : "Failed", "error"));
              }}
            >
              Clear cart
            </button>
          </div>

          {warnings.length ? (
            <div className="mb-4 space-y-2" role="status">
              {warnings.map((w, i) => (
                <div
                  key={`${w.code}-${w.product_id ?? i}`}
                  className={`rounded-[var(--radius-md)] border px-3 py-2 text-sm ${
                    w.severity
                      ? "border-[var(--color-error)] bg-[var(--color-error-soft)] text-[var(--color-error)]"
                      : "border-[var(--color-warning,#f59e0b)] bg-[#fffbeb] text-[#92400e]"
                  }`}
                >
                  {w.message}
                </div>
              ))}
            </div>
          ) : null}

          <div className="space-y-3">
            {items.map((item) => (
              <div
                key={item.id}
                className="flex gap-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-4"
              >
                <div className="relative h-24 w-24 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                  <SafeImage src={item.thumbnail_url} alt="" fill className="object-cover" sizes="96px" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{item.name}</p>
                  <p className="mt-1 text-sm font-bold text-[var(--color-primary-deep)]">
                    {money(item.unit_price)}
                  </p>
                  <div className="mt-3 flex flex-wrap items-center gap-3">
                    <div className="inline-flex items-center rounded-full border border-[var(--color-border)]">
                      <button
                        type="button"
                        className="h-9 w-9 disabled:opacity-50"
                        aria-label="Decrease quantity"
                        disabled={mutating || item.quantity <= 1}
                        onClick={() => {
                          void updateItem(item.id, item.quantity - 1).catch((e) =>
                            toast(e instanceof Error ? e.message : "Update failed", "error"),
                          );
                        }}
                      >
                        −
                      </button>
                      <span className="min-w-8 text-center text-sm font-semibold">{item.quantity}</span>
                      <button
                        type="button"
                        className="h-9 w-9 disabled:opacity-50"
                        aria-label="Increase quantity"
                        disabled={mutating}
                        onClick={() =>
                          void updateItem(item.id, item.quantity + 1).catch((e) =>
                            toast(e instanceof Error ? e.message : "Update failed", "error"),
                          )
                        }
                      >
                        +
                      </button>
                    </div>
                    <button
                      type="button"
                      className="text-sm font-medium text-[var(--color-error)] disabled:opacity-50"
                      disabled={mutating}
                      onClick={() =>
                        void removeItem(item.id).catch((e) =>
                          toast(e instanceof Error ? e.message : "Remove failed", "error"),
                        )
                      }
                    >
                      Remove
                    </button>
                    <button
                      type="button"
                      className="text-sm font-medium text-[var(--color-primary)] disabled:opacity-50"
                      disabled={mutating}
                      onClick={() => {
                        if (!user) {
                          router.push(loginHref("/cart"));
                          return;
                        }
                        void moveToWishlist(item.id)
                          .then(() => fetchWishlist())
                          .then(() => toast("Moved to wishlist"))
                          .catch((e) =>
                            toast(e instanceof Error ? e.message : "Could not move", "error"),
                          );
                      }}
                    >
                      Move to wishlist
                    </button>
                  </div>
                </div>
                <p className="font-semibold">{money(item.line_total)}</p>
              </div>
            ))}
          </div>
        </div>

        <aside className="h-fit rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-5 shadow-[var(--shadow-sm)]">
          <h2 className="display text-3xl text-[var(--color-primary-deep)]">Price details</h2>
          <div className="mt-4 space-y-2 text-sm">
            <Row label="Subtotal" value={money(cart?.subtotal ?? 0)} />
            <Row label="Discount" value={`-${money(cart?.discount_total ?? 0)}`} />
            <Row label="Tax" value={money(cart?.tax_total ?? 0)} />
            <Row label="Total" value={money(cart?.grand_total ?? 0)} strong />
          </div>

          {free?.enabled !== false ? (
            <div className="mt-4 rounded-[var(--radius-md)] bg-[var(--color-primary-soft)] px-3 py-3 text-sm">
              {!qualifies ? (
                <p>
                  Add {money(remaining)} more to unlock <strong>FREE delivery</strong>.
                </p>
              ) : (
                <p>
                  You’ve unlocked <strong>FREE delivery</strong>.
                </p>
              )}
              <div className="mt-2 h-2 overflow-hidden rounded-full bg-white/70">
                <div
                  className="h-full rounded-full bg-[var(--color-primary)]"
                  style={{
                    width: `${Math.min(100, threshold > 0 ? ((cart?.subtotal ?? 0) / threshold) * 100 : 100)}%`,
                  }}
                />
              </div>
            </div>
          ) : null}

          {cart?.coupon_code ? (
            <div className="mt-4 flex items-center justify-between rounded-[var(--radius-md)] border border-[var(--color-success)] bg-[var(--color-success-soft)] px-3 py-2 text-sm">
              <span>✓ {cart.coupon_code} applied</span>
              <button
                type="button"
                className="font-semibold text-[var(--color-primary-deep)]"
                onClick={() =>
                  void removeCoupon()
                    .then(() => toast("Coupon removed", "info"))
                    .catch((e) => toast(e instanceof Error ? e.message : "Failed", "error"))
                }
              >
                Remove
              </button>
            </div>
          ) : (
            <form onSubmit={onCoupon} className="mt-4 flex gap-2">
              <Input
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="Coupon code"
                aria-label="Coupon code"
              />
              <Button type="submit" variant="outline">
                Apply
              </Button>
            </form>
          )}

          <div className="mt-4 space-y-2">
            {checkoutBlocked ? (
              <Button fullWidth disabled>
                Fix cart issues to checkout
              </Button>
            ) : (
              <Link href="/checkout">
                <Button fullWidth>Proceed to checkout</Button>
              </Link>
            )}
            <Link href="/shop">
              <Button fullWidth variant="outline">
                Continue shopping
              </Button>
            </Link>
          </div>
        </aside>
      </div>
    </section>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div
      className={`flex justify-between ${strong ? "border-t border-[var(--color-border)] pt-2 text-base font-bold" : "text-[var(--color-muted)]"}`}
    >
      <span>{label}</span>
      <span className={strong ? "text-[var(--color-primary-deep)]" : ""}>{value}</span>
    </div>
  );
}
