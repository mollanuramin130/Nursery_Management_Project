import Link from "next/link";
import { ListingControls, ListingSidebar } from "@/components/catalog/ListingControls";
import { ProductCard } from "@/components/product/ProductCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { buildProductsQuery, type CatalogParams } from "@/lib/catalog-query";
import { serverGet } from "@/lib/server-api";
import type { Category, Pagination, ProductSummary } from "@/lib/types";

export default async function CategoryPage({
  params,
  searchParams,
}: {
  params: Promise<{ slug: string }>;
  searchParams: Promise<Record<string, string | undefined>>;
}) {
  const { slug } = await params;
  const sp = await searchParams;
  const categoryRes = await serverGet<Category & { products?: ProductSummary[] }>(
    `/categories/${slug}`,
    { revalidate: 60 },
  );
  const category = categoryRes?.data;

  const filterParams: CatalogParams = {
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
    category: slug,
  };

  const hasExtraFilters = Boolean(
    filterParams.product_type ||
      filterParams.min_price ||
      filterParams.max_price ||
      filterParams.indoor_outdoor ||
      filterParams.sunlight ||
      filterParams.water_requirement ||
      filterParams.difficulty_level ||
      filterParams.availability ||
      filterParams.min_rating ||
      filterParams.sort ||
      filterParams.page,
  );

  let products: ProductSummary[] = [];
  let pagination: Pagination | undefined;

  if (!hasExtraFilters && category?.products?.length) {
    products = category.products;
  } else {
    const qs = buildProductsQuery(filterParams);
    const list = await serverGet<ProductSummary[]>(`/products?${qs}`, { revalidate: 30 });
    products = list?.data ?? [];
    pagination = list?.meta?.pagination as Pagination | undefined;
  }

  const basePath = `/category/${slug}`;
  const listingParams: CatalogParams = { ...filterParams };
  delete listingParams.category;

  return (
    <section className="section">
      <div className="container">
        <nav className="mb-4 text-sm text-[var(--color-muted)]">
          <Link href="/" className="hover:text-[var(--color-primary)]">
            Home
          </Link>
          <span className="mx-2">/</span>
          <Link href="/shop" className="hover:text-[var(--color-primary)]">
            Shop
          </Link>
          <span className="mx-2">/</span>
          <span>{category?.name ?? slug}</span>
        </nav>

        <div className="mb-6 overflow-hidden rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--color-primary-soft)]">
          <div className="grid gap-4 p-6 md:grid-cols-[1.2fr_0.8fr] md:items-center md:p-8">
            <div>
              <h1 className="display text-4xl text-[var(--color-primary-deep)] md:text-5xl">
                {category?.name ?? slug.replace(/-/g, " ")}
              </h1>
              <p className="mt-3 max-w-xl text-[var(--color-ink-soft)]">
                {category?.description ||
                  "Beautiful plants and essentials curated for this collection."}
              </p>
            </div>
            {category?.children?.length ? (
              <div className="flex flex-wrap gap-2">
                {category.children.slice(0, 8).map((c) => (
                  <Link
                    key={c.id}
                    href={`/category/${c.slug}`}
                    className="rounded-full border border-[var(--color-border)] bg-white px-3.5 py-2 text-sm font-medium"
                  >
                    {c.name}
                  </Link>
                ))}
              </div>
            ) : null}
          </div>
        </div>

        <div className="grid gap-8 lg:grid-cols-[260px_1fr]">
          <ListingSidebar basePath={basePath} params={listingParams} hideType />
          <div>
            <ListingControls
              basePath={basePath}
              params={listingParams}
              total={pagination?.total ?? products.length}
              hideType
            />
            {products.length ? (
              <div className="product-grid">
                {products.map((p) => (
                  <ProductCard key={p.id} product={p} />
                ))}
              </div>
            ) : (
              <EmptyState
                title="Nothing here yet"
                description="Try another filter or explore the full shop."
                actionHref="/shop"
                actionLabel="Browse shop"
              />
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
