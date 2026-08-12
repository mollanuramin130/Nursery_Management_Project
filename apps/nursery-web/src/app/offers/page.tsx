import Image from "next/image";
import Link from "next/link";
import { ProductCard } from "@/components/product/ProductCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { Badge } from "@/components/ui/Badge";
import { serverGet } from "@/lib/server-api";
import type { OffersFeed } from "@/lib/types";

export const metadata = {
  title: "Special Offers | GreenLeaf Nursery",
  description: "Limited-time offers, seasonal collections, and sale plants from GreenLeaf Nursery.",
};

function formatEnds(iso?: string | null) {
  if (!iso) return null;
  return new Date(iso).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export default async function OffersPage() {
  const offersRes = await serverGet<OffersFeed>("/offers?per_page=16", { revalidate: 60 });
  const feed = offersRes?.data;
  const featured = feed?.featured_campaigns ?? [];
  const upcoming = feed?.upcoming_campaigns ?? [];
  const sales = feed?.sale_products ?? [];
  const coupons = feed?.public_coupons ?? [];
  const hero = featured[0];

  return (
    <section className="section">
      <div className="container">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)] md:text-5xl">
            Special offers
          </h1>
          <p>Grow more, spend less — live campaigns and sale prices from the nursery catalog.</p>
        </div>

        <div className="mb-8 flex flex-wrap gap-2">
          {[
            { href: "/offers", label: "All offers" },
            { href: "/shop?on_sale=1&product_type=plant", label: "Plants" },
            { href: "/shop?on_sale=1&product_type=pot", label: "Pots" },
            { href: "/shop?on_sale=1&product_type=soil", label: "Soil" },
            { href: "/shop?on_sale=1&product_type=accessory", label: "Accessories" },
            { href: "/find-your-plant", label: "Find your plant" },
          ].map((c) => (
            <Link
              key={c.href + c.label}
              href={c.href}
              className="rounded-full border border-[var(--color-border)] bg-white px-3.5 py-1.5 text-sm font-semibold hover:border-[var(--color-primary)]"
            >
              {c.label}
            </Link>
          ))}
        </div>

        {hero ? (
          <Link
            href={`/campaigns/${hero.slug}`}
            className="group relative mb-14 block min-h-[280px] overflow-hidden rounded-[var(--radius-xl)] bg-[var(--color-primary-deep)] text-white"
          >
            {(hero.banner_image || hero.image_url) ? (
              <Image
                src={(hero.banner_image || hero.image_url)!}
                alt=""
                fill
                className="object-cover opacity-55 transition-transform duration-500 group-hover:scale-105"
                sizes="100vw"
                priority
              />
            ) : null}
            <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/30 to-transparent" />
            <div className="relative flex min-h-[280px] flex-col justify-end p-6 md:p-10">
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-white/80">
                Featured collection
              </p>
              <h2 className="display mt-1 text-3xl md:text-5xl">{hero.title}</h2>
              {hero.short_description || hero.subtitle ? (
                <p className="mt-2 max-w-xl text-white/90">
                  {hero.short_description || hero.subtitle}
                </p>
              ) : null}
              {hero.ends_at ? (
                <p className="mt-3 text-sm text-white/75">Valid until {formatEnds(hero.ends_at)}</p>
              ) : null}
              <span className="mt-5 inline-flex w-fit rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[var(--color-primary-deep)]">
                Explore collection
              </span>
            </div>
          </Link>
        ) : null}

        {featured.length > 1 ? (
          <div className="mb-14">
            <div className="section-head">
              <h2>Seasonal campaigns</h2>
              <p>Curated collections — not every product is discounted.</p>
            </div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {featured.slice(1).map((c) => (
                <Link
                  key={c.id}
                  href={`/campaigns/${c.slug}`}
                  className="group relative min-h-[200px] overflow-hidden rounded-[var(--radius-lg)] bg-[var(--color-primary-deep)] text-white"
                >
                  {(c.image_url || c.banner_image) ? (
                    <Image
                      src={(c.image_url || c.banner_image)!}
                      alt=""
                      fill
                      className="object-cover opacity-50 transition group-hover:scale-105"
                      sizes="33vw"
                    />
                  ) : null}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent" />
                  <div className="relative flex h-full min-h-[200px] flex-col justify-end p-5">
                    {c.is_featured ? <Badge tone="success">Featured</Badge> : null}
                    <h3 className="display mt-2 text-2xl">{c.title}</h3>
                    {c.subtitle ? <p className="mt-1 text-sm text-white/85">{c.subtitle}</p> : null}
                  </div>
                </Link>
              ))}
            </div>
          </div>
        ) : null}

        {sales.length ? (
          <div className="mb-14">
            <div className="section-head">
              <h2>Sale plants & products</h2>
              <p>Prices shown are server catalog prices with compare-at savings.</p>
            </div>
            <div className="product-grid">
              {sales.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
            <div className="mt-6">
              <Link href="/shop?on_sale=1" className="font-semibold text-[var(--color-primary)]">
                View all sale items →
              </Link>
            </div>
          </div>
        ) : (
          <EmptyState
            title="No sale items right now"
            description="Browse seasonal campaigns or the full catalog."
            actionHref="/shop"
            actionLabel="Browse shop"
          />
        )}

        {coupons.length ? (
          <div className="mb-14">
            <div className="section-head">
              <h2>Public coupons</h2>
              <p>Enter these codes in cart — separate from automatic sale prices.</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {coupons.map((c) => (
                <div
                  key={c.code}
                  className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
                >
                  <p className="font-mono text-lg font-bold text-[var(--color-primary-deep)]">
                    {c.code}
                  </p>
                  <p className="mt-1 text-sm text-[var(--color-muted)]">
                    {c.discount_type === "percent"
                      ? `${c.discount_value}% off`
                      : `₹${c.discount_value} off`}
                    {c.min_order_amount ? ` · min ₹${c.min_order_amount}` : ""}
                  </p>
                </div>
              ))}
            </div>
          </div>
        ) : null}

        {upcoming.length ? (
          <div>
            <div className="section-head">
              <h2>Coming soon</h2>
              <p>Scheduled campaigns — activation is controlled by the server.</p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              {upcoming.map((c) => (
                <div
                  key={c.id}
                  className="rounded-[var(--radius-lg)] border border-dashed border-[var(--color-border)] bg-[var(--color-surface-muted)] p-5"
                >
                  <p className="text-xs font-bold uppercase tracking-wider text-[var(--color-muted)]">
                    Starts {formatEnds(c.starts_at) ?? "soon"}
                  </p>
                  <h3 className="mt-1 text-xl font-bold">{c.title}</h3>
                  {c.subtitle ? <p className="mt-1 text-sm text-[var(--color-muted)]">{c.subtitle}</p> : null}
                  <Link
                    href={`/campaigns/${c.slug}`}
                    className="mt-3 inline-block text-sm font-semibold text-[var(--color-primary)]"
                  >
                    Preview collection →
                  </Link>
                </div>
              ))}
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}
