"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ProductCard } from "@/components/product/ProductCard";
import { getRecentlyViewed, type RecentProduct } from "@/lib/recently-viewed";
import type { ProductSummary } from "@/lib/types";

/** Home / discovery rail — only renders when local history exists. */
export function ContinueShoppingRail() {
  const [items, setItems] = useState<RecentProduct[]>([]);

  useEffect(() => {
    setItems(getRecentlyViewed().slice(0, 8));
  }, []);

  if (!items.length) return null;

  return (
    <section className="section pt-0">
      <div className="container">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div className="section-head !mb-0">
            <h2>Continue shopping</h2>
            <p>Products you viewed recently on this device.</p>
          </div>
          <Link href="/shop" className="hidden text-sm font-semibold text-[var(--color-primary)] md:inline">
            Browse all →
          </Link>
        </div>
        <div className="product-grid">
          {items.map((p) => (
            <ProductCard
              key={p.id}
              product={
                {
                  id: p.id,
                  slug: p.slug,
                  name: p.name,
                  price: p.price ?? 0,
                  currency: p.currency ?? "INR",
                  thumbnail_url: p.thumbnail_url,
                  stock_status: "in_stock",
                  product_type: "plant",
                } as ProductSummary
              }
            />
          ))}
        </div>
      </div>
    </section>
  );
}
