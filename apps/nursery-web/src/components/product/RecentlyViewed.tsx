"use client";

import { useEffect, useState } from "react";
import { ProductCard } from "@/components/product/ProductCard";
import {
  getRecentlyViewed,
  trackRecentlyViewed,
  type RecentProduct,
} from "@/lib/recently-viewed";
import type { ProductSummary } from "@/lib/types";

/** Records a product view (PDP) and optionally renders a recently-viewed rail. */
export function RecentlyViewedTracker({
  product,
  showRail = false,
  excludeId,
}: {
  product?: RecentProduct;
  showRail?: boolean;
  excludeId?: number;
}) {
  const [items, setItems] = useState<RecentProduct[]>([]);

  useEffect(() => {
    if (product) trackRecentlyViewed(product);
    const list = getRecentlyViewed().filter((p) => p.id !== excludeId);
    setItems(list);
  }, [product, excludeId]);

  if (!showRail || !items.length) return null;

  return (
    <div className="mt-16">
      <div className="section-head">
        <h2>Recently viewed</h2>
        <p>Pick up where you left off.</p>
      </div>
      <div className="product-grid">
        {items.slice(0, 4).map((p) => (
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
  );
}
