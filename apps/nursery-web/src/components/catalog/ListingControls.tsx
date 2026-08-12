"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { FilterPanel, SORT_OPTIONS } from "@/components/catalog/FilterPanel";
import { Drawer } from "@/components/ui/Drawer";
import {
  catalogHref,
  countActiveFilters,
  type CatalogParams,
} from "@/lib/catalog-query";

export function ListingControls({
  basePath,
  params,
  total,
  hideType,
}: {
  basePath: string;
  params: CatalogParams;
  total?: number;
  hideType?: boolean;
}) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const active = countActiveFilters(params);
  const sort = params.sort ?? "newest";

  return (
    <>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-[var(--color-muted)]">
          {typeof total === "number" ? `${total} products` : "Browse catalog"}
        </p>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => setOpen(true)}
            className="inline-flex min-h-11 items-center rounded-full border border-[var(--color-border-strong)] bg-white px-4 text-sm font-semibold lg:hidden"
          >
            Filters{active ? ` (${active})` : ""}
          </button>
          <label className="inline-flex min-h-11 items-center gap-2 rounded-full border border-[var(--color-border)] bg-white px-3 text-sm">
            <span className="text-[var(--color-muted)]">Sort</span>
            <select
              className="bg-transparent font-semibold outline-none"
              value={sort}
              onChange={(e) => {
                router.push(
                  catalogHref(basePath, params, {
                    sort: e.target.value === "newest" ? undefined : e.target.value,
                  }),
                );
              }}
              aria-label="Sort products"
            >
              {SORT_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
        </div>
      </div>

      <Drawer open={open} onClose={() => setOpen(false)} title="Filters" side="bottom">
        <FilterPanel
          basePath={basePath}
          params={params}
          hideType={hideType}
          onNavigate={() => setOpen(false)}
        />
        <div className="sticky bottom-0 mt-4 border-t border-[var(--color-border)] bg-white pt-3">
          <button
            type="button"
            className="w-full rounded-full bg-[var(--color-primary-deep)] py-3 text-sm font-semibold text-white"
            onClick={() => setOpen(false)}
          >
            Show results
          </button>
          {active ? (
            <Link
              href={catalogHref(basePath, {}, { q: params.q, sort: params.sort })}
              className="mt-2 block text-center text-sm font-semibold text-[var(--color-primary)]"
              onClick={() => setOpen(false)}
            >
              Clear filters
            </Link>
          ) : null}
        </div>
      </Drawer>
    </>
  );
}

export function ListingSidebar({
  basePath,
  params,
  hideType,
}: {
  basePath: string;
  params: CatalogParams;
  hideType?: boolean;
}) {
  return (
    <aside className="hidden lg:block">
      <div className="sticky top-24 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
        <FilterPanel basePath={basePath} params={params} hideType={hideType} />
      </div>
    </aside>
  );
}
