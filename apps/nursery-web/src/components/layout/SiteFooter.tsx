import Link from "next/link";

const storeName = process.env.NEXT_PUBLIC_STORE_NAME ?? "GreenLeaf Nursery";

export function SiteFooter() {
  return (
    <>
      <section className="border-y border-[var(--color-border)] bg-[var(--color-primary-soft)]">
        <div className="container flex flex-col items-start justify-between gap-4 py-10 md:flex-row md:items-center">
          <div>
            <h2 className="display text-3xl text-[var(--color-primary-deep)] md:text-4xl">
              Let’s grow something beautiful.
            </h2>
            <p className="mt-2 max-w-xl text-[var(--color-ink-soft)]">
              Plants, gardening essentials, and clear care guidance — all in one place.
            </p>
          </div>
          <Link
            href="/shop"
            className="inline-flex min-h-11 items-center rounded-full bg-[var(--color-primary-deep)] px-5 text-sm font-semibold text-white hover:bg-[var(--color-primary)]"
          >
            Explore plants
          </Link>
        </div>
      </section>

      <footer className="bg-[var(--color-primary-deep)] text-[#e7f0ea]">
        <div className="container grid gap-10 py-12 md:grid-cols-4">
          <div className="md:col-span-1">
            <p className="display text-3xl text-white">{storeName}</p>
            <p className="mt-3 text-sm leading-relaxed text-[#c5d8cc]">
              Healthy plants and gardening essentials for Indian homes — from balconies to indoor corners.
            </p>
          </div>
          <FooterCol
            title="Shop"
            links={[
              { href: "/shop", label: "All products" },
              { href: "/categories", label: "Categories" },
              { href: "/category/indoor-plants", label: "Indoor plants" },
              { href: "/find-your-plant", label: "Find your plant" },
              { href: "/offers", label: "Offers & campaigns" },
            ]}
          />
          <FooterCol
            title="Help"
            links={[
              { href: "/account/orders", label: "Track order" },
              { href: "/account", label: "My account" },
              { href: "/wishlist", label: "Wishlist" },
              { href: "/register", label: "Create account" },
            ]}
          />
          <div>
            <p className="mb-3 text-sm font-semibold text-white">Gardening tips</p>
            <p className="text-sm text-[#c5d8cc]">
              Seasonal care notes and special offers — coming to your inbox soon.
            </p>
            <p className="mt-4 text-xs text-[#9fb5a8]">
              Secure checkout · Fresh packing · Plant-care guidance
            </p>
          </div>
        </div>
        <div className="border-t border-white/10 py-4 text-center text-xs text-[#9fb5a8]">
          © {new Date().getFullYear()} {storeName}. All rights reserved.
        </div>
      </footer>
    </>
  );
}

function FooterCol({
  title,
  links,
}: {
  title: string;
  links: Array<{ href: string; label: string }>;
}) {
  return (
    <div>
      <p className="mb-3 text-sm font-semibold text-white">{title}</p>
      <ul className="space-y-2 text-sm text-[#c5d8cc]">
        {links.map((l) => (
          <li key={l.href}>
            <Link href={l.href} className="hover:text-white">
              {l.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
