import Link from "next/link";
import { ListingControls, ListingSidebar } from "@/components/catalog/ListingControls";
import { ProductCard } from "@/components/product/ProductCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { buildProductsQuery, type CatalogParams } from "@/lib/catalog-query";
import { serverGet } from "@/lib/server-api";
import type { Pagination, ProductSummary } from "@/lib/types";

export const metadata = { title: "Shop" };

export default async function ShopPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | undefined>>;
}) {
  const sp = await searchParams;
  const params: CatalogParams = {
    page: sp.page,
    product_type: sp.product_type ?? sp.type,
    type: sp.type,
    min_price: sp.min_price,
    max_price: sp.max_price,
    indoor_outdoor: sp.indoor_outdoor,
    sunlight: sp.sunlight,
    water_requirement: sp.water_requirement,
    difficulty_level: sp.difficulty_level,
    availability: sp.availability,
    min_rating: sp.min_rating,
    on_sale: sp.on_sale,
    sort: sp.sort,
    q: sp.q,
  };

  const qs = buildProductsQuery(params);
  const res = await serverGet<ProductSummary[]>(`/products?${qs}`, { revalidate: 30 });
  const items = res?.data ?? [];
  const pagination = res?.meta?.pagination as Pagination | undefined;
  const basePath = "/shop";

  return (
    <section className="section">
      <div className="container">
        <nav className="mb-4 text-sm text-[var(--color-muted)]">
          <Link href="/" className="hover:text-[var(--color-primary)]">
            Home
          </Link>
          <span className="mx-2">/</span>
          <span>Shop</span>
        </nav>
        <div className="mb-6 section-head !mb-6">
          <h1 className="display text-4xl text-[var(--color-primary-deep)] md:text-5xl">Shop all</h1>
          <p className="mt-2 text-[var(--color-muted)]">
            Plants, pots, soil, and kits — live from the nursery catalog.
          </p>
        </div>

        <div className="grid gap-8 lg:grid-cols-[260px_1fr]">
          <ListingSidebar basePath={basePath} params={params} />
          <div>
            <ListingControls basePath={basePath} params={params} total={pagination?.total} />
            {items.length ? (
              <div className="product-grid">
                {items.map((p) => (
                  <ProductCard key={p.id} product={p} />
                ))}
              </div>
            ) : (
              <EmptyState
                title="No products match"
                description="Try clearing filters or browse a different category."
                actionHref="/shop"
                actionLabel="Clear filters"
              />
            )}

            {pagination && pagination.last_page > 1 ? (
              <div className="mt-10 flex items-center justify-center gap-3">
                {pagination.current_page > 1 ? (
                  <Link
                    href={`/shop?${buildProductsQuery({ ...params, page: String(pagination.current_page - 1) })}`}
                    className="rounded-full border border-[var(--color-border)] px-4 py-2 text-sm font-semibold"
                  >
                    Previous
                  </Link>
                ) : null}
                <span className="text-sm text-[var(--color-muted)]">
                  Page {pagination.current_page} of {pagination.last_page}
                </span>
                {pagination.current_page < pagination.last_page ? (
                  <Link
                    href={`/shop?${buildProductsQuery({ ...params, page: String(pagination.current_page + 1) })}`}
                    className="rounded-full border border-[var(--color-border)] px-4 py-2 text-sm font-semibold"
                  >
                    Next
                  </Link>
                ) : null}
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </section>
  );
}
