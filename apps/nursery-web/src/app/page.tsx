import Image from "next/image";
import Link from "next/link";
import { ProductCard } from "@/components/product/ProductCard";
import { ContinueShoppingRail } from "@/components/home/ContinueShoppingRail";
import { bannerHref } from "@/lib/deep-link";
import { serverGet } from "@/lib/server-api";
import type { Category, HomeData } from "@/lib/types";

const storeName = process.env.NEXT_PUBLIC_STORE_NAME ?? "GreenLeaf Nursery";

const SHOP_BY_NEED = [
  { title: "Beginner plants", q: "beginner", blurb: "Easy care for new plant parents" },
  { title: "Low light", q: "low light", blurb: "For rooms without harsh sun" },
  { title: "Air purifying", q: "air purifying", blurb: "Fresher indoor air" },
  { title: "Balcony greens", q: "balcony", blurb: "Compact outdoor picks" },
];

export default async function HomePage() {
  const homeRes = await serverGet<HomeData>("/home", { revalidate: 60 });
  const catsRes = await serverGet<Category[]>("/categories", { revalidate: 120 });
  const home = homeRes?.data;
  const hero = home?.banners?.[0];
  const products = home?.featured_products ?? [];
  const bestSellers = home?.best_sellers ?? [];
  const newArrivals = home?.new_arrivals ?? [];
  const recommended = home?.recommended_for_you ?? [];
  const indoor = home?.indoor_plants ?? [];
  const outdoor = home?.outdoor_plants ?? [];
  const lowMaintenance = home?.low_maintenance ?? [];
  const campaigns = home?.campaigns ?? [];
  const categories =
    (home?.categories?.length ? home.categories : catsRes?.data ?? []).filter(
      (c) => !c.parent_id,
    ).slice(0, 8);

  return (
    <>
      <section className="relative overflow-hidden bg-[var(--color-primary-deep)] text-white">
        <div className="absolute inset-0">
          {hero?.image_url ? (
            <Image
              src={hero.image_url}
              alt=""
              fill
              priority
              sizes="100vw"
              className="object-cover opacity-55"
            />
          ) : null}
          <div className="absolute inset-0 bg-[linear-gradient(105deg,rgba(15,61,40,0.92)_0%,rgba(15,61,40,0.55)_55%,rgba(15,61,40,0.35)_100%)]" />
        </div>

        <div className="relative container grid min-h-[78vh] items-end gap-8 py-14 md:min-h-[72vh] md:grid-cols-[1.1fr_0.9fr] md:items-center md:py-20">
          <div className="animate-up max-w-xl">
            <p className="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#d5e8da]">
              {storeName}
            </p>
            <h1 className="display text-[clamp(2.8rem,8vw,5.2rem)] text-white">
              Bring nature home
            </h1>
            <p className="mt-4 max-w-md text-base leading-relaxed text-[#dce9e0] md:text-lg">
              Healthy plants, pots, and care guidance — curated for Indian balconies, desks, and sunny corners.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href={bannerHref(hero)}
                className="inline-flex min-h-12 items-center rounded-full bg-white px-6 text-sm font-semibold text-[var(--color-primary-deep)] hover:bg-[#f3f7f4]"
              >
                {hero?.link_type === "campaign" ? "Explore collection" : "Shop plants"}
              </Link>
              <Link
                href="/find-your-plant"
                className="inline-flex min-h-12 items-center rounded-full border border-white/40 px-6 text-sm font-semibold text-white hover:bg-white/10"
              >
                Find your plant
              </Link>
              <Link
                href="/offers"
                className="inline-flex min-h-12 items-center rounded-full border border-white/40 px-6 text-sm font-semibold text-white hover:bg-white/10"
              >
                Special offers
              </Link>
            </div>
          </div>
          <div className="hidden md:block" aria-hidden />
        </div>
      </section>

      <ContinueShoppingRail />

      <section className="section">
        <div className="container">
          <div className="section-head">
            <h2>Shop by category</h2>
            <p>Start with the space you want to green up.</p>
          </div>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
            {categories.map((c) => (
              <Link
                key={c.id}
                href={`/category/${c.slug}`}
                className="group relative min-h-36 overflow-hidden rounded-[var(--radius-lg)] bg-[var(--color-surface-muted)] md:min-h-44"
              >
                {c.image_url ? (
                  <Image
                    src={c.image_url}
                    alt=""
                    fill
                    className="object-cover transition duration-500 group-hover:scale-105"
                    sizes="25vw"
                  />
                ) : null}
                <div className="absolute inset-0 bg-gradient-to-t from-black/65 via-black/15 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-3 text-white md:p-4">
                  <h3 className="font-semibold">{c.name}</h3>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="section pt-0">
        <div className="container">
          <div className="mb-6 flex items-end justify-between gap-4">
            <div className="section-head !mb-0">
              <h2>Featured greens</h2>
              <p>Bestsellers and easy starters from the live catalog.</p>
            </div>
            <Link href="/shop" className="hidden text-sm font-semibold text-[var(--color-primary)] md:inline">
              View all →
            </Link>
          </div>
          {products.length ? (
            <div className="product-grid">
              {products.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          ) : (
            <p className="text-[var(--color-muted)]">
              Catalog unavailable. Is the API running on port 8000?
            </p>
          )}
        </div>
      </section>

      {bestSellers.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="mb-6 flex items-end justify-between gap-4">
              <div className="section-head !mb-0">
                <h2>Best sellers</h2>
                <p>Top-rated plants customers keep coming back for.</p>
              </div>
              <Link
                href="/shop?sort=popular"
                className="hidden text-sm font-semibold text-[var(--color-primary)] md:inline"
              >
                View all →
              </Link>
            </div>
            <div className="product-grid">
              {bestSellers.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {newArrivals.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="mb-6 flex items-end justify-between gap-4">
              <div className="section-head !mb-0">
                <h2>New arrivals</h2>
                <p>Fresh stock just in from the nursery.</p>
              </div>
              <Link
                href="/shop?sort=newest"
                className="hidden text-sm font-semibold text-[var(--color-primary)] md:inline"
              >
                View all →
              </Link>
            </div>
            <div className="product-grid">
              {newArrivals.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {recommended.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="section-head">
              <h2>Easy care picks</h2>
              <p>Beginner-friendly plants from the live catalog (rule-based, not purchase history).</p>
            </div>
            <div className="product-grid">
              {recommended.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {indoor.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="section-head">
              <h2>Indoor plants</h2>
              <p>From plant profiles marked indoor.</p>
            </div>
            <div className="product-grid">
              {indoor.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {outdoor.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="section-head">
              <h2>Outdoor & balcony</h2>
              <p>From plant profiles marked outdoor or both.</p>
            </div>
            <div className="product-grid">
              {outdoor.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {lowMaintenance.length && !recommended.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="section-head">
              <h2>Low-maintenance plants</h2>
              <p>Easy difficulty plants currently in stock.</p>
            </div>
            <div className="product-grid">
              {lowMaintenance.slice(0, 8).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      <section className="section pt-0">
        <div className="container">
          <div className="section-head">
            <h2>Shop by need</h2>
            <p>Know your space — we’ll help you find the right plant.</p>
          </div>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {SHOP_BY_NEED.map((item) => (
              <Link
                key={item.q}
                href={`/search?q=${encodeURIComponent(item.q)}`}
                className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-5 transition hover:border-[var(--color-primary)] hover:shadow-[var(--shadow-sm)]"
              >
                <h3 className="font-semibold text-[var(--color-primary-deep)]">{item.title}</h3>
                <p className="mt-1 text-sm text-[var(--color-muted)]">{item.blurb}</p>
              </Link>
            ))}
          </div>
          <div className="mt-4">
            <Link
              href="/find-your-plant"
              className="inline-flex min-h-11 items-center rounded-full border border-[var(--color-primary)] px-5 text-sm font-semibold text-[var(--color-primary-deep)] hover:bg-[var(--color-primary-soft)]"
            >
              Not sure? Use Find your plant →
            </Link>
          </div>
        </div>
      </section>

      {campaigns.length ? (
        <section className="section pt-0">
          <div className="container">
            <div className="section-head">
              <h2>Seasonal collections</h2>
              <p>Curated campaigns for the week ahead.</p>
            </div>
            <div className="grid gap-4 md:grid-cols-3">
              {campaigns.slice(0, 3).map((c) => (
                <Link
                  key={c.id}
                  href={`/campaigns/${c.slug}`}
                  className="group relative min-h-64 overflow-hidden rounded-[var(--radius-xl)]"
                >
                  {c.image_url ? (
                    <Image
                      src={c.image_url}
                      alt=""
                      fill
                      className="object-cover transition duration-700 group-hover:scale-105"
                      sizes="(max-width:768px) 100vw, 33vw"
                    />
                  ) : null}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent" />
                  <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                    <h3 className="display text-3xl">{c.title}</h3>
                    {c.subtitle ? <p className="mt-1 text-sm text-white/85">{c.subtitle}</p> : null}
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      <section className="section pt-0">
        <div className="container">
          <div className="grid gap-4 rounded-[var(--radius-xl)] bg-[var(--color-surface)] p-6 shadow-[var(--shadow-sm)] md:grid-cols-4 md:p-8">
            {[
              ["Fresh plants", "Packed with care from nursery to doorstep"],
              ["Clear plant care", "Sun, water, and difficulty at a glance"],
              ["Secure payments", "COD and online checkout options"],
              ["Easy returns", "Help when something isn’t right"],
            ].map(([title, body]) => (
              <div key={title}>
                <h3 className="font-semibold text-[var(--color-primary-deep)]">{title}</h3>
                <p className="mt-1 text-sm text-[var(--color-muted)]">{body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section pt-0">
        <div className="container grid gap-6 rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--color-primary-soft)] p-6 md:grid-cols-[1.2fr_0.8fr] md:items-center md:p-8">
          <div>
            <h2 className="display text-3xl text-[var(--color-primary-deep)] md:text-4xl">
              Plant care, season by season
            </h2>
            <p className="mt-2 max-w-xl text-[var(--color-ink-soft)]">
              Not sure what thrives in your balcony light? Use Find your plant, or explore beginner
              and low-light collections.
            </p>
            <div className="mt-5 flex flex-wrap gap-3">
              <Link
                href="/find-your-plant"
                className="inline-flex min-h-11 items-center rounded-full bg-[var(--color-primary-deep)] px-5 text-sm font-semibold text-white"
              >
                Find your plant
              </Link>
              <Link
                href="/search?q=beginner"
                className="inline-flex min-h-11 items-center rounded-full border border-[var(--color-primary)] px-5 text-sm font-semibold text-[var(--color-primary-deep)]"
              >
                Beginner picks
              </Link>
            </div>
          </div>
          <div className="rounded-[var(--radius-lg)] bg-white p-5">
            <p className="text-sm font-semibold text-[var(--color-primary-deep)]">Newsletter</p>
            <p className="mt-1 text-sm text-[var(--color-muted)]">
              Seasonal gardening tips and real offers — no spam.
            </p>
            <form
              className="mt-4 flex flex-col gap-2 sm:flex-row"
              action="/offers"
              method="get"
            >
              <label htmlFor="home-newsletter" className="sr-only">
                Email
              </label>
              <input
                id="home-newsletter"
                name="email"
                type="email"
                required
                placeholder="you@example.com"
                className="min-h-11 flex-1 rounded-full border border-[var(--color-border)] px-4 text-sm outline-none focus:border-[var(--color-primary)]"
              />
              <button
                type="submit"
                className="min-h-11 rounded-full bg-[var(--color-primary-deep)] px-5 text-sm font-semibold text-white"
              >
                Get tips
              </button>
            </form>
            <p className="mt-2 text-xs text-[var(--color-muted)]">
              Newsletter API not wired yet — you’ll land on Offers for now.
            </p>
          </div>
        </div>
      </section>
    </>
  );
}
