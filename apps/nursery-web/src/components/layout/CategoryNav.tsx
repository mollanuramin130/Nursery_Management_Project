"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { usePathname } from "next/navigation";
import { apiGet } from "@/lib/api";
import type { Category } from "@/lib/types";
import { cn } from "@/lib/cn";

const FALLBACK: Category[] = [
  { id: 1, name: "Indoor Plants", slug: "indoor-plants" },
  { id: 2, name: "Outdoor Plants", slug: "outdoor-plants" },
  { id: 3, name: "Flowering", slug: "flowering-plants" },
  { id: 4, name: "Pots", slug: "pots-planters" },
  { id: 5, name: "Soil & Fertilizer", slug: "soil-fertilizers" },
  { id: 6, name: "Tools", slug: "gardening-tools" },
];

export function CategoryNav() {
  const pathname = usePathname();
  const [cats, setCats] = useState<Category[]>(FALLBACK);
  const [openSlug, setOpenSlug] = useState<string | null>(null);
  const closeTimer = useRef<number | null>(null);

  useEffect(() => {
    void apiGet<Category[]>("/categories")
      .then((res) => {
        const top = (res.data ?? []).filter((c) => !c.parent_id).slice(0, 10);
        if (top.length) setCats(top);
      })
      .catch(() => undefined);
  }, []);

  function open(slug: string) {
    if (closeTimer.current) window.clearTimeout(closeTimer.current);
    setOpenSlug(slug);
  }

  function scheduleClose() {
    if (closeTimer.current) window.clearTimeout(closeTimer.current);
    closeTimer.current = window.setTimeout(() => setOpenSlug(null), 160);
  }

  return (
    <nav
      aria-label="Categories"
      className="sticky top-[57px] z-[var(--z-sticky)] border-b border-[var(--color-border)] bg-[var(--color-surface)] md:top-[65px]"
    >
      <div className="container relative">
        <ul className="flex gap-1 overflow-x-auto py-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <li>
            <Link
              href="/categories"
              className={cn(
                "inline-flex whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary-deep)]",
                pathname === "/categories" && "bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]",
              )}
            >
              Categories
            </Link>
          </li>
          <li>
            <Link
              href="/shop"
              className={cn(
                "inline-flex whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary-deep)]",
                pathname === "/shop" && "bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]",
              )}
            >
              Shop all
            </Link>
          </li>
          {cats.map((c) => {
            const href = `/category/${c.slug}`;
            const active = pathname === href || pathname.startsWith(`${href}/`);
            const hasChildren = Boolean(c.children?.length);
            return (
              <li
                key={c.slug}
                className="relative"
                onMouseEnter={() => hasChildren && open(c.slug)}
                onMouseLeave={scheduleClose}
              >
                <Link
                  href={href}
                  className={cn(
                    "inline-flex whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary-deep)]",
                    active && "bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]",
                  )}
                  aria-expanded={hasChildren ? openSlug === c.slug : undefined}
                  aria-haspopup={hasChildren ? "true" : undefined}
                >
                  {c.name}
                  {hasChildren ? <span className="ml-1 hidden text-[10px] md:inline">▾</span> : null}
                </Link>

                {hasChildren && openSlug === c.slug ? (
                  <div
                    className="absolute left-0 top-full z-[var(--z-dropdown)] hidden min-w-[240px] pt-2 md:block"
                    onMouseEnter={() => open(c.slug)}
                    onMouseLeave={scheduleClose}
                  >
                    <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-3 shadow-[var(--shadow-md)]">
                      <p className="mb-2 px-2 text-xs font-bold uppercase tracking-wide text-[var(--color-muted)]">
                        {c.name}
                      </p>
                      <ul className="grid gap-0.5">
                        {c.children!.map((child) => (
                          <li key={child.id}>
                            <Link
                              href={`/category/${child.slug}`}
                              className="block rounded-[var(--radius-md)] px-3 py-2.5 text-sm hover:bg-[var(--color-primary-soft)]"
                              onClick={() => setOpenSlug(null)}
                            >
                              {child.name}
                            </Link>
                          </li>
                        ))}
                        <li>
                          <Link
                            href={href}
                            className="block rounded-[var(--radius-md)] px-3 py-2.5 text-sm font-semibold text-[var(--color-primary)]"
                            onClick={() => setOpenSlug(null)}
                          >
                            View all {c.name}
                          </Link>
                        </li>
                      </ul>
                    </div>
                  </div>
                ) : null}
              </li>
            );
          })}
          <li>
            <Link
              href="/offers"
              className={cn(
                "inline-flex whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary-deep)]",
                pathname === "/offers" && "bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]",
              )}
            >
              Offers
            </Link>
          </li>
        </ul>
      </div>
    </nav>
  );
}
