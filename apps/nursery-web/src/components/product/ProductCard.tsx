"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ProductPrice } from "@/components/product/ProductPrice";
import { ProductRating } from "@/components/product/ProductRating";
import { SafeImage } from "@/components/ui/SafeImage";
import { loginHref } from "@/lib/auth-redirect";
import type { ProductSummary } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { useUiStore } from "@/store/ui";
import { useWishlistStore } from "@/store/wishlist";

function primaryBadge(badges?: string[], stock?: string, compareAt?: number | null, price?: number) {
  if (stock === "out_of_stock") return { label: "Out of stock", tone: "error" as const };
  if (badges?.includes("sale") || (compareAt != null && price != null && compareAt > price))
    return { label: "Sale", tone: "warning" as const };
  if (badges?.includes("bestseller")) return { label: "Bestseller", tone: "brand" as const };
  if (badges?.includes("new") || badges?.includes("new-arrival"))
    return { label: "New", tone: "success" as const };
  if (stock === "low_stock" || badges?.includes("low-stock"))
    return { label: "Low stock", tone: "warning" as const };
  if (badges?.[0]) return { label: badges[0].replace(/-/g, " "), tone: "neutral" as const };
  return null;
}

export function ProductCard({ product }: { product: ProductSummary }) {
  const addItem = useCartStore((s) => s.addItem);
  const user = useAuthStore((s) => s.user);
  const wishHas = useWishlistStore((s) => s.has(product.id));
  const wishAdd = useWishlistStore((s) => s.add);
  const wishRemove = useWishlistStore((s) => s.remove);
  const toast = useToastStore((s) => s.push);
  const openMiniCart = useUiStore((s) => s.openMiniCart);
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [wishBusy, setWishBusy] = useState(false);
  const badge = primaryBadge(
    product.badges,
    product.stock_status,
    product.compare_at_price,
    product.price,
  );
  const out = product.stock_status === "out_of_stock";

  async function onAdd() {
    if (out || busy) return;
    setBusy(true);
    try {
      await addItem(product.id, 1);
      openMiniCart(product.name);
      toast("Added to cart");
    } catch (e) {
      toast(e instanceof Error ? e.message : "Could not add", "error");
    } finally {
      setBusy(false);
    }
  }

  async function onWish(e: React.MouseEvent) {
    e.preventDefault();
    e.stopPropagation();
    if (!user) {
      router.push(loginHref(`/product/${product.slug}`));
      return;
    }
    // QA-36-004: ignore rapid double-taps while in flight.
    if (wishBusy) return;
    setWishBusy(true);
    try {
      if (wishHas) {
        await wishRemove(product.id);
        toast("Removed from wishlist", "info");
      } else {
        await wishAdd(product.id);
        toast("Saved to wishlist");
      }
    } catch (err) {
      toast(err instanceof Error ? err.message : "Wishlist failed", "error");
    } finally {
      setWishBusy(false);
    }
  }

  return (
    <article className="group flex h-full flex-col">
      <div className="relative overflow-hidden rounded-[var(--radius-lg)] bg-[var(--color-surface-muted)]">
        <Link href={`/product/${product.slug}`} className="block">
          <div className="relative aspect-[4/5]">
            <SafeImage
              src={product.thumbnail_url}
              alt={product.name}
              fill
              sizes="(max-width:768px) 50vw, 25vw"
              className="object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]"
            />
          </div>
        </Link>
        {badge ? (
          <div className="absolute left-2.5 top-2.5">
            <Badge tone={badge.tone}>{badge.label}</Badge>
          </div>
        ) : null}
        <button
          type="button"
          onClick={onWish}
          disabled={wishBusy}
          aria-label={wishHas ? "Remove from wishlist" : "Add to wishlist"}
          className="absolute right-2.5 top-2.5 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-lg shadow-[var(--shadow-sm)] backdrop-blur transition hover:bg-white disabled:opacity-60"
        >
          {wishHas ? "♥" : "♡"}
        </button>
      </div>

      <div className="mt-3 flex flex-1 flex-col gap-1.5">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-muted)]">
          {product.product_type?.replace(/_/g, " ")}
        </p>
        <Link href={`/product/${product.slug}`} className="font-semibold leading-snug hover:text-[var(--color-primary)]">
          {product.name}
        </Link>
        <ProductRating avg={product.rating_avg} count={product.rating_count} />
        <ProductPrice
          price={product.price}
          compareAt={product.compare_at_price}
          currency={product.currency}
        />
        <div className="mt-auto pt-2">
          <Button
            fullWidth
            size="sm"
            variant={out ? "outline" : "primary"}
            disabled={out || busy}
            onClick={onAdd}
          >
            {out ? "Out of stock" : busy ? "Adding…" : "Add to cart"}
          </Button>
        </div>
      </div>
    </article>
  );
}
