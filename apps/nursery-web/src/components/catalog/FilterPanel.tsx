"use client";

import Link from "next/link";
import {
  catalogHref,
  countActiveFilters,
  type CatalogParams,
} from "@/lib/catalog-query";

const PRODUCT_TYPES = [
  { value: "plant", label: "Plants" },
  { value: "tree", label: "Trees" },
  { value: "seed", label: "Seeds" },
  { value: "pot", label: "Pots" },
  { value: "soil", label: "Soil" },
  { value: "fertilizer", label: "Fertilizers" },
  { value: "tool", label: "Tools" },
  { value: "kit", label: "Kits" },
];

const PLACEMENT = [
  { value: "indoor", label: "Indoor" },
  { value: "outdoor", label: "Outdoor" },
  { value: "both", label: "Indoor & outdoor" },
];

const SUNLIGHT = [
  { value: "low", label: "Low light" },
  { value: "bright_indirect", label: "Bright indirect" },
  { value: "full_sun", label: "Full sun" },
];

const WATER = [
  { value: "low", label: "Low" },
  { value: "medium", label: "Moderate" },
  { value: "high", label: "High" },
];

const DIFFICULTY = [
  { value: "easy", label: "Beginner" },
  { value: "moderate", label: "Intermediate" },
  { value: "advanced", label: "Expert" },
];

const AVAILABILITY = [
  { value: "in_stock", label: "In stock" },
  { value: "low_stock", label: "Low stock" },
];

const PRICE_PRESETS = [
  { min: undefined as string | undefined, max: "299", label: "Under ₹299" },
  { min: "300", max: "599", label: "₹300 – ₹599" },
  { min: "600", max: "999", label: "₹600 – ₹999" },
  { min: "1000", max: undefined as string | undefined, label: "₹1000+" },
];

function ChipLink({
  href,
  active,
  children,
  onNavigate,
}: {
  href: string;
  active?: boolean;
  children: React.ReactNode;
  onNavigate?: () => void;
}) {
  return (
    <Link
      href={href}
      onClick={onNavigate}
      className={`inline-flex min-h-10 items-center rounded-full border px-3.5 text-sm ${
        active
          ? "border-[var(--color-primary-deep)] bg-[var(--color-primary-soft)] font-semibold text-[var(--color-primary-deep)]"
          : "border-[var(--color-border)] bg-white text-[var(--color-ink-soft)] hover:border-[var(--color-primary)]"
      }`}
    >
      {children}
    </Link>
  );
}

function FilterGroup({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="border-b border-[var(--color-border)] py-4 last:border-0">
      <p className="mb-2.5 text-xs font-bold uppercase tracking-wide text-[var(--color-muted)]">
        {title}
      </p>
      <div className="flex flex-wrap gap-2">{children}</div>
    </div>
  );
}

export function FilterPanel({
  basePath,
  params,
  hideType,
  onNavigate,
}: {
  basePath: string;
  params: CatalogParams;
  hideType?: boolean;
  onNavigate?: () => void;
}) {
  const active = countActiveFilters(params);
  const clearHref = catalogHref(basePath, {}, { q: params.q, sort: params.sort });

  return (
    <div>
      <div className="mb-2 flex items-center justify-between gap-2">
        <p className="text-sm font-semibold text-[var(--color-ink)]">
          Filters{active ? ` (${active})` : ""}
        </p>
        {active ? (
          <Link
            href={clearHref}
            onClick={onNavigate}
            className="text-sm font-semibold text-[var(--color-primary)]"
          >
            Clear all
          </Link>
        ) : null}
      </div>

      {!hideType ? (
        <FilterGroup title="Product type">
          {PRODUCT_TYPES.map((t) => {
            const isActive = params.product_type === t.value || params.type === t.value;
            return (
              <ChipLink
                key={t.value}
                href={catalogHref(basePath, params, {
                  product_type: isActive ? undefined : t.value,
                })}
                active={isActive}
                onNavigate={onNavigate}
              >
                {t.label}
              </ChipLink>
            );
          })}
        </FilterGroup>
      ) : null}

      <FilterGroup title="Price">
        {PRICE_PRESETS.map((p) => {
          const isActive =
            (params.min_price ?? undefined) === p.min &&
            (params.max_price ?? undefined) === p.max;
          return (
            <ChipLink
              key={p.label}
              href={catalogHref(basePath, params, {
                min_price: isActive ? undefined : p.min,
                max_price: isActive ? undefined : p.max,
              })}
              active={isActive}
              onNavigate={onNavigate}
            >
              {p.label}
            </ChipLink>
          );
        })}
      </FilterGroup>

      <FilterGroup title="Placement">
        {PLACEMENT.map((o) => (
          <ChipLink
            key={o.value}
            href={catalogHref(basePath, params, {
              indoor_outdoor: params.indoor_outdoor === o.value ? undefined : o.value,
            })}
            active={params.indoor_outdoor === o.value}
            onNavigate={onNavigate}
          >
            {o.label}
          </ChipLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Sunlight">
        {SUNLIGHT.map((o) => (
          <ChipLink
            key={o.value}
            href={catalogHref(basePath, params, {
              sunlight: params.sunlight === o.value ? undefined : o.value,
            })}
            active={params.sunlight === o.value}
            onNavigate={onNavigate}
          >
            {o.label}
          </ChipLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Water">
        {WATER.map((o) => (
          <ChipLink
            key={o.value}
            href={catalogHref(basePath, params, {
              water_requirement:
                params.water_requirement === o.value ? undefined : o.value,
            })}
            active={params.water_requirement === o.value}
            onNavigate={onNavigate}
          >
            {o.label}
          </ChipLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Difficulty">
        {DIFFICULTY.map((o) => (
          <ChipLink
            key={o.value}
            href={catalogHref(basePath, params, {
              difficulty_level:
                params.difficulty_level === o.value ? undefined : o.value,
            })}
            active={params.difficulty_level === o.value}
            onNavigate={onNavigate}
          >
            {o.label}
          </ChipLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Availability">
        {AVAILABILITY.map((o) => (
          <ChipLink
            key={o.value}
            href={catalogHref(basePath, params, {
              availability: params.availability === o.value ? undefined : o.value,
            })}
            active={params.availability === o.value}
            onNavigate={onNavigate}
          >
            {o.label}
          </ChipLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Rating">
        {[4, 3].map((r) => (
          <ChipLink
            key={r}
            href={catalogHref(basePath, params, {
              min_rating: params.min_rating === String(r) ? undefined : String(r),
            })}
            active={params.min_rating === String(r)}
            onNavigate={onNavigate}
          >
            {r}+ stars
          </ChipLink>
        ))}
      </FilterGroup>
    </div>
  );
}

export const SORT_OPTIONS = [
  { value: "newest", label: "Newest" },
  { value: "price_asc", label: "Price: Low to high" },
  { value: "price_desc", label: "Price: High to low" },
  { value: "rating", label: "Top rated" },
  { value: "popular", label: "Popular" },
] as const;
