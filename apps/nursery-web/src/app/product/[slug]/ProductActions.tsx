"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { useUiStore } from "@/store/ui";
import { useWishlistStore } from "@/store/wishlist";

export function ProductActions({
  productId,
  productName,
  productSlug,
  price,
  outOfStock,
}: {
  productId: number;
  productName: string;
  productSlug: string;
  price: number;
  outOfStock?: boolean;
}) {
  const addItem = useCartStore((s) => s.addItem);
  const user = useAuthStore((s) => s.user);
  const wishHas = useWishlistStore((s) => s.has(productId));
  const wishAdd = useWishlistStore((s) => s.add);
  const wishRemove = useWishlistStore((s) => s.remove);
  const toast = useToastStore((s) => s.push);
  const openMiniCart = useUiStore((s) => s.openMiniCart);
  const router = useRouter();
  const [qty, setQty] = useState(1);
  const [busy, setBusy] = useState(false);
  const [wishBusy, setWishBusy] = useState(false);

  // QA-38: lift toasts above the mobile sticky Buy/Cart strip.
  useEffect(() => {
    const root = document.documentElement;
    root.style.setProperty("--sticky-cta-h", "4.5rem");
    return () => root.style.removeProperty("--sticky-cta-h");
  }, []);

  async function addToCart(openDrawer = true): Promise<boolean> {
    if (outOfStock || busy) return false;
    setBusy(true);
    try {
      await addItem(productId, qty);
      if (openDrawer) openMiniCart(productName);
      toast("Added to cart");
      return true;
    } catch (e) {
      toast(e instanceof Error ? e.message : "Failed", "error");
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function buyNow() {
    const ok = await addToCart(false);
    if (ok) router.push("/checkout");
  }

  async function toggleWish() {
    if (!user) {
      router.push(loginHref(`/product/${productSlug}`));
      return;
    }
    // QA-37-003: single-flight PDP wishlist toggle.
    if (wishBusy) return;
    setWishBusy(true);
    try {
      if (wishHas) {
        await wishRemove(productId);
        toast("Removed from wishlist", "info");
      } else {
        await wishAdd(productId);
        toast("Saved to wishlist");
      }
    } catch (e) {
      toast(e instanceof Error ? e.message : "Failed", "error");
    } finally {
      setWishBusy(false);
    }
  }

  return (
    <>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <label htmlFor="qty" className="text-sm font-semibold text-[var(--color-muted)]">
            Qty
          </label>
          <Input
            id="qty"
            type="number"
            min={1}
            value={qty}
            onChange={(e) => setQty(Math.max(1, Number(e.target.value) || 1))}
            className="!w-24"
          />
        </div>
        <div className="hidden flex-wrap gap-3 md:flex">
          <Button onClick={() => void addToCart()} disabled={busy || outOfStock} className="min-w-[10rem]">
            {outOfStock ? "Out of stock" : busy ? "Adding…" : "Add to cart"}
          </Button>
          <Button variant="secondary" onClick={() => void buyNow()} disabled={busy || outOfStock}>
            Buy now
          </Button>
          <Button
            variant="outline"
            onClick={toggleWish}
            disabled={wishBusy}
            aria-pressed={wishHas}
            className={
              wishHas
                ? "border-[var(--color-wishlist-active)] text-[var(--color-wishlist-active)]"
                : undefined
            }
          >
            {wishBusy ? "…" : wishHas ? "♥ Saved" : "♡ Wishlist"}
          </Button>
        </div>
        <div className="flex flex-wrap gap-3 md:hidden">
          <Button
            variant="outline"
            onClick={toggleWish}
            disabled={wishBusy}
            aria-pressed={wishHas}
            className={
              wishHas
                ? "border-[var(--color-wishlist-active)] text-[var(--color-wishlist-active)]"
                : undefined
            }
          >
            {wishBusy ? "…" : wishHas ? "♥ Saved" : "♡ Wishlist"}
          </Button>
        </div>
      </div>

      <div className="fixed inset-x-0 bottom-[var(--bottom-nav-h)] z-[35] border-t border-[var(--color-border)] bg-[var(--color-surface)]/95 p-3 backdrop-blur md:hidden">
        <div className="flex items-center gap-2">
          <div className="min-w-0 flex-1">
            <p className="truncate text-xs text-[var(--color-muted)]">{productName}</p>
            <p className="font-bold text-[var(--color-primary-deep)]">{money(price)}</p>
          </div>
          <Button size="sm" variant="outline" onClick={() => void addToCart()} disabled={busy || outOfStock}>
            Cart
          </Button>
          <Button size="sm" onClick={() => void buyNow()} disabled={busy || outOfStock}>
            Buy
          </Button>
        </div>
      </div>
    </>
  );
}
