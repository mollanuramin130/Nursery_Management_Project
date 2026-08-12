import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ProductActions } from "./ProductActions";
import { ProductCard } from "@/components/product/ProductCard";
import { ProductGallery } from "@/components/product/ProductGallery";
import { PlantCareGuide } from "@/components/product/PlantCareGuide";
import { ProductPrice } from "@/components/product/ProductPrice";
import { ProductRating } from "@/components/product/ProductRating";
import { ProductReviews } from "@/components/product/ProductReviews";
import { RecentlyViewedTracker } from "@/components/product/RecentlyViewed";
import { StockAlertButton } from "@/components/product/StockAlertButton";
import { SubscribePanel } from "@/components/product/SubscribePanel";
import { Badge } from "@/components/ui/Badge";
import { Breadcrumb } from "@/components/ui/Breadcrumb";
import { serverGet } from "@/lib/server-api";
import type { ProductDetail, ProductSummary } from "@/lib/types";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverGet<ProductDetail>(`/products/${slug}`, { revalidate: 60 });
  const p = res?.data;
  return {
    title: p?.name ?? "Product",
    description: p?.description ?? undefined,
    openGraph: p
      ? {
          title: p.name,
          description: p.description ?? undefined,
          images: p.thumbnail_url ? [{ url: p.thumbnail_url }] : undefined,
        }
      : undefined,
  };
}

export default async function ProductPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverGet<ProductDetail>(`/products/${slug}`, { revalidate: 30 });
  const product = res?.data;
  if (!product) notFound();

  const plant = product.plant;
  const out = product.stock_status === "out_of_stock";
  const low = product.stock_status === "low_stock";

  let related = product.related ?? [];
  if (!related.length) {
    const rel = await serverGet<ProductSummary[]>(`/products/${product.id}/related`, {
      revalidate: 60,
    });
    related = rel?.data ?? [];
  }

  let recommended: ProductSummary[] = [];
  const rec = await serverGet<ProductSummary[]>(
    `/products/${product.id}/recommendations?type=similar`,
    { revalidate: 60 },
  );
  recommended = rec?.data ?? [];

  return (
    <section className="section pb-28 md:pb-16">
      <div className="container">
        <Breadcrumb
          items={[
            { href: "/", label: "Home" },
            { href: "/shop", label: "Shop" },
            { label: product.name },
          ]}
        />

        <div className="grid gap-10 lg:grid-cols-[1.05fr_0.95fr]">
          <ProductGallery
            images={product.images}
            name={product.name}
            fallback={product.thumbnail_url}
          />

          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--color-muted)]">
              {product.product_type}
            </p>
            <h1 className="display mt-2 text-[clamp(2.2rem,5vw,3.6rem)] text-[var(--color-primary-deep)]">
              {product.name}
            </h1>
            {plant?.scientific_name ? (
              <p className="mt-1 italic text-[var(--color-muted)]">{plant.scientific_name}</p>
            ) : null}
            <div className="mt-3 flex flex-wrap items-center gap-3">
              <ProductRating avg={product.rating_avg} count={product.rating_count} />
              {out ? (
                <Badge tone="error">Out of stock</Badge>
              ) : low ? (
                <Badge tone="warning">Only a few left</Badge>
              ) : (
                <Badge tone="success">In stock</Badge>
              )}
              {(product.badges ?? []).slice(0, 3).map((b) => (
                <Badge key={b} tone={b === "sale" || b === "low-stock" ? "warning" : "brand"}>
                  {b.replace(/-/g, " ")}
                </Badge>
              ))}
            </div>
            {product.sku ? (
              <p className="mt-2 text-xs text-[var(--color-muted)]">SKU {product.sku}</p>
            ) : null}
            {!out && product.available_qty != null && product.available_qty > 0 && product.available_qty <= 8 ? (
              <p className="mt-1 text-xs font-medium text-[var(--color-warning,#b45309)]">
                {product.available_qty} available
              </p>
            ) : null}
            <div className="mt-5">
              <ProductPrice
                price={product.price}
                compareAt={product.compare_at_price}
                currency={product.currency}
                size="lg"
              />
            </div>
            {product.description ? (
              <p className="mt-5 text-[var(--color-ink-soft)] leading-relaxed">{product.description}</p>
            ) : null}

            <div className="mt-8">
              <ProductActions
                productId={product.id}
                productName={product.name}
                productSlug={product.slug}
                price={product.price}
                outOfStock={out}
              />
              {out ? <StockAlertButton productId={product.id} productSlug={product.slug} /> : null}
              {product.subscription_enabled && (product.subscription_plans?.length ?? 0) > 0 ? (
                <SubscribePanel
                  productId={product.id}
                  productSlug={product.slug}
                  oneTimePrice={product.price}
                  plans={product.subscription_plans!}
                  outOfStock={out}
                />
              ) : null}
            </div>
          </div>
        </div>

        <div className="mt-16 border-t border-[var(--color-border)] pt-12">
          <PlantCareGuide slug={slug} />
        </div>

        <div className="mt-16 border-t border-[var(--color-border)] pt-12">
          <Suspense fallback={<p className="text-sm text-[var(--color-muted)]">Loading reviews…</p>}>
            <ProductReviews productId={product.id} productSlug={product.slug} />
          </Suspense>
        </div>

        {related.length ? (
          <div className="mt-16">
            <div className="section-head">
              <h2>You may also like</h2>
              <p>Similar picks from the nursery.</p>
            </div>
            <div className="product-grid">
              {related.slice(0, 4).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        ) : null}

        {recommended.length ? (
          <div className="mt-16">
            <div className="section-head">
              <h2>Complete your garden</h2>
              <p>Recommended companions based on this product.</p>
            </div>
            <div className="product-grid">
              {recommended.slice(0, 4).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        ) : null}

        <RecentlyViewedTracker
          product={{
            id: product.id,
            slug: product.slug,
            name: product.name,
            thumbnail_url: product.thumbnail_url,
            price: product.price,
            currency: product.currency,
          }}
          showRail
          excludeId={product.id}
        />
      </div>
    </section>
  );
}
