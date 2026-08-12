"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect } from "react";
import { money } from "@/lib/format";
import { useCartStore } from "@/store/cart";
import { useUiStore } from "@/store/ui";

export function MiniCartDrawer() {
  const open = useUiStore((s) => s.miniCartOpen);
  const close = useUiStore((s) => s.closeMiniCart);
  const lastAdded = useUiStore((s) => s.lastAddedName);
  const cart = useCartStore((s) => s.cart);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") close();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, close]);

  if (!open) return null;

  const free = cart?.free_delivery;
  const remaining = free?.remaining ?? 0;
  const qualifies = free?.qualifies ?? remaining <= 0;

  return (
    <div className="fixed inset-0 z-[70]">
      <button
        type="button"
        className="absolute inset-0 bg-black/40 animate-fade"
        aria-label="Close cart drawer"
        onClick={close}
      />
      <aside
        className="absolute inset-y-0 right-0 flex w-[min(420px,100%)] flex-col bg-[var(--color-surface)] shadow-[var(--shadow-lg)] animate-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Cart drawer"
      >
        <div className="flex items-center justify-between border-b border-[var(--color-border)] px-5 py-4">
          <div>
            <p className="font-semibold text-[var(--color-primary-deep)]">Added to cart</p>
            {lastAdded ? <p className="text-sm text-[var(--color-muted)]">{lastAdded}</p> : null}
          </div>
          <button type="button" onClick={close} className="text-2xl leading-none" aria-label="Close">
            ×
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-4">
          <ul className="space-y-4">
            {(cart?.items ?? []).slice(0, 4).map((item) => (
              <li key={item.id} className="flex gap-3">
                <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                  {item.thumbnail_url ? (
                    <Image src={item.thumbnail_url} alt="" fill className="object-cover" sizes="64px" />
                  ) : null}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">{item.name}</p>
                  <p className="text-sm text-[var(--color-muted)]">
                    Qty {item.quantity} · {money(item.line_total)}
                  </p>
                </div>
              </li>
            ))}
          </ul>

          {free?.enabled !== false ? (
            <div className="mt-5 rounded-[var(--radius-md)] bg-[var(--color-primary-soft)] px-4 py-3 text-sm">
              {!qualifies ? (
                <p>
                  Add {money(remaining)} more to unlock <strong>FREE delivery</strong>.
                </p>
              ) : (
                <p>
                  You’ve unlocked <strong>FREE delivery</strong>.
                </p>
              )}
            </div>
          ) : null}
        </div>

        <div className="space-y-3 border-t border-[var(--color-border)] px-5 py-4">
          <div className="flex justify-between text-sm">
            <span className="text-[var(--color-muted)]">Subtotal</span>
            <span className="font-bold">{money(cart?.subtotal ?? 0)}</span>
          </div>
          <Link
            href="/cart"
            onClick={close}
            className="inline-flex w-full min-h-11 items-center justify-center rounded-full border border-[var(--color-border-strong)] text-sm font-semibold"
          >
            View cart
          </Link>
          <Link
            href="/checkout"
            onClick={close}
            className="inline-flex w-full min-h-11 items-center justify-center rounded-full bg-[var(--color-primary-deep)] text-sm font-semibold text-white"
          >
            Checkout
          </Link>
        </div>
      </aside>
    </div>
  );
}
