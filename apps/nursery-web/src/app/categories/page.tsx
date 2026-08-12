import Image from "next/image";
import Link from "next/link";
import { EmptyState } from "@/components/ui/EmptyState";
import { serverGet } from "@/lib/server-api";
import type { Category } from "@/lib/types";

export const metadata = {
  title: "Categories | GreenLeaf Nursery",
  description: "Browse indoor plants, outdoor plants, pots, seeds, and gardening essentials.",
};

export default async function CategoriesPage() {
  const res = await serverGet<Category[]>("/categories", { revalidate: 120 });
  const categories = (res?.data ?? []).filter((c) => !c.parent_id);

  return (
    <section className="section">
      <div className="container">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)] md:text-5xl">
            Categories
          </h1>
          <p>Explore the nursery by plant type, space, and gardening essentials.</p>
        </div>

        {!categories.length ? (
          <EmptyState
            title="Categories unavailable"
            description="The catalog API did not return categories. Try the full shop instead."
            actionHref="/shop"
            actionLabel="Shop all"
          />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((c) => (
              <article
                key={c.id}
                className="overflow-hidden rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--color-surface)]"
              >
                <Link href={`/category/${c.slug}`} className="group block">
                  <div className="relative min-h-44 bg-[var(--color-surface-muted)]">
                    {c.image_url ? (
                      <Image
                        src={c.image_url}
                        alt={c.name}
                        fill
                        className="object-cover transition duration-500 group-hover:scale-105"
                        sizes="(max-width:768px) 100vw, 33vw"
                      />
                    ) : null}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
                    <div className="absolute inset-x-0 bottom-0 p-4 text-white">
                      <h2 className="display text-2xl">{c.name}</h2>
                      {c.description ? (
                        <p className="mt-1 line-clamp-2 text-sm text-white/85">{c.description}</p>
                      ) : null}
                    </div>
                  </div>
                </Link>
                {c.children?.length ? (
                  <ul className="flex flex-wrap gap-2 p-4">
                    {c.children.slice(0, 8).map((child) => (
                      <li key={child.id}>
                        <Link
                          href={`/category/${child.slug}`}
                          className="inline-flex min-h-9 items-center rounded-full border border-[var(--color-border)] px-3 text-sm text-[var(--color-ink-soft)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary-deep)]"
                        >
                          {child.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <div className="p-4">
                    <Link
                      href={`/category/${c.slug}`}
                      className="text-sm font-semibold text-[var(--color-primary)]"
                    >
                      Shop {c.name} →
                    </Link>
                  </div>
                )}
              </article>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
