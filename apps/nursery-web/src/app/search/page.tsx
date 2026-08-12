import Link from "next/link";
import { ListingControls, ListingSidebar } from "@/components/catalog/ListingControls";
import { ProductCard } from "@/components/product/ProductCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { buildProductsQuery, type CatalogParams } from "@/lib/catalog-query";
import { serverGet } from "@/lib/server-api";
import type { Pagination, ProductSummary } from "@/lib/types";

export const metadata = { title: "Search" };

type SearchAssist = {
  query: string;
  categories: Array<{ type: string; label: string; slug: string }>;
  filters: Array<{ type: string; label: string; params: Record<string, string> }>;
  popular_products: ProductSummary[];
  popular_searches: string[];
  plant_finder?: { available: boolean; path: string; hint: string };
};

function filterHref(params: Record<string, string>): string {
  const qs = new URLSearchParams(params).toString();
  return qs ? `/shop?${qs}` : "/shop";
}

export default async function SearchPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | undefined>>;
}) {
  const sp = await searchParams;
  const q = sp.q ?? "";
  const params: CatalogParams = {
    q: q || undefined,
    page: sp.page,
    product_type: sp.product_type ?? sp.type,
    min_price: sp.min_price,
    max_price: sp.max_price,
    indoor_outdoor: sp.indoor_outdoor,
    sunlight: sp.sunlight,
    water_requirement: sp.water_requirement,
    difficulty_level: sp.difficulty_level,
    availability: sp.availability,
    min_rating: sp.min_rating,
    sort: sp.sort,
  };

  let items: ProductSummary[] = [];
  let pagination: Pagination | undefined;
  let assist: SearchAssist | null = null;

  if (q) {
    const qs = buildProductsQuery(params);
    const res = await serverGet<
      ProductSummary[] | { products?: ProductSummary[]; items?: ProductSummary[] }
    >(`/search?${qs}`, { revalidate: false });
    const data = res?.data;
    if (Array.isArray(data)) items = data;
    else items = data?.products ?? data?.items ?? [];
    pagination = res?.meta?.pagination as Pagination | undefined;

    if (items.length === 0) {
      const assistRes = await serverGet<SearchAssist>(
        `/search/assist?q=${encodeURIComponent(q)}`,
        { revalidate: 60 },
      );
      assist = assistRes?.data ?? null;
    }
  }

  const basePath = "/search";

  return (
    <section className="section">
      <div className="container">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Search</h1>
          <p>{q ? `Results for “${q}”` : "Search plants, pots, and care essentials."}</p>
        </div>

        {!q ? (
          <div className="flex flex-wrap gap-2">
            {["money plant", "beginner", "indoor", "aloe", "snake"].map((term) => (
              <Link
                key={term}
                href={`/search?q=${encodeURIComponent(term)}`}
                className="rounded-full border border-[var(--color-border)] bg-white px-4 py-2 text-sm"
              >
                {term}
              </Link>
            ))}
          </div>
        ) : (
          <div className="grid gap-8 lg:grid-cols-[260px_1fr]">
            <ListingSidebar basePath={basePath} params={params} />
            <div>
              <ListingControls basePath={basePath} params={params} total={pagination?.total ?? items.length} />
              {items.length === 0 ? (
                <div className="space-y-8">
                  <EmptyState
                    title={`No results for “${q}”`}
                    description="Try a broader keyword, a guided plant match, or browse popular in-stock plants from the catalog."
                    actionHref="/find-your-plant"
                    actionLabel="Find your plant"
                  />

                  {assist?.filters?.length ? (
                    <div>
                      <h2 className="text-sm font-semibold">Try these filters</h2>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {assist.filters.map((f) => (
                          <Link
                            key={f.label}
                            href={filterHref(f.params)}
                            className="rounded-full border border-[var(--color-border)] bg-white px-4 py-2 text-sm"
                          >
                            {f.label}
                          </Link>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  {assist?.categories?.length ? (
                    <div>
                      <h2 className="text-sm font-semibold">Related categories</h2>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {assist.categories.map((c) => (
                          <Link
                            key={c.slug}
                            href={`/category/${c.slug}`}
                            className="rounded-full border border-[var(--color-border)] bg-white px-4 py-2 text-sm"
                          >
                            {c.label}
                          </Link>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  {assist?.popular_searches?.length ? (
                    <div>
                      <h2 className="text-sm font-semibold">Popular searches</h2>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {assist.popular_searches.map((term) => (
                          <Link
                            key={term}
                            href={`/search?q=${encodeURIComponent(term)}`}
                            className="rounded-full border border-[var(--color-border)] bg-white px-4 py-2 text-sm"
                          >
                            {term}
                          </Link>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  {assist?.popular_products?.length ? (
                    <div>
                      <h2 className="mb-3 text-sm font-semibold">Popular products</h2>
                      <div className="product-grid">
                        {assist.popular_products.slice(0, 8).map((p) => (
                          <ProductCard key={p.id} product={p} />
                        ))}
                      </div>
                    </div>
                  ) : null}
                </div>
              ) : (
                <div className="product-grid">
                  {items.map((p) => (
                    <ProductCard key={p.id} product={p} />
                  ))}
                </div>
              )}

              {pagination && pagination.last_page > 1 ? (
                <div className="mt-10 flex items-center justify-center gap-3">
                  {pagination.current_page > 1 ? (
                    <Link
                      href={`/search?${buildProductsQuery({ ...params, page: String(pagination.current_page - 1) })}`}
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
                      href={`/search?${buildProductsQuery({ ...params, page: String(pagination.current_page + 1) })}`}
                      className="rounded-full border border-[var(--color-border)] px-4 py-2 text-sm font-semibold"
                    >
                      Next
                    </Link>
                  ) : null}
                </div>
              ) : null}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
