/** Build product list query from URL search params (API-aligned filters). */

export type CatalogFilterKey =
  | "q"
  | "category"
  | "product_type"
  | "min_price"
  | "max_price"
  | "indoor_outdoor"
  | "sunlight"
  | "water_requirement"
  | "difficulty_level"
  | "availability"
  | "min_rating"
  | "on_sale"
  | "sort"
  | "page"
  | "type";

export type CatalogParams = Partial<Record<CatalogFilterKey, string | undefined>>;

export function buildProductsQuery(params: CatalogParams, perPage = 24): string {
  const qs = new URLSearchParams();
  qs.set("per_page", String(perPage));

  const page = params.page ?? "1";
  if (page && page !== "1") qs.set("page", page);

  const type = params.product_type ?? params.type;
  if (type) qs.set("product_type", type);

  for (const key of [
    "q",
    "category",
    "min_price",
    "max_price",
    "indoor_outdoor",
    "sunlight",
    "water_requirement",
    "difficulty_level",
    "availability",
    "min_rating",
    "on_sale",
    "sort",
  ] as const) {
    const v = params[key];
    if (v) qs.set(key, v);
  }

  return qs.toString();
}

export function catalogHref(
  basePath: string,
  current: CatalogParams,
  patch: CatalogParams,
  dropPage = true,
): string {
  const next: CatalogParams = { ...current, ...patch };
  if (dropPage) delete next.page;

  // Normalize legacy `type` → product_type; drop empties
  if ("product_type" in patch && !patch.product_type) {
    delete next.product_type;
    delete next.type;
  } else if (next.type && !next.product_type) {
    next.product_type = next.type;
  }
  delete next.type;

  for (const key of Object.keys(next) as CatalogFilterKey[]) {
    if (!next[key]) delete next[key];
  }

  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(next)) {
    if (v && k !== "category") qs.set(k, v);
  }
  const s = qs.toString();
  return s ? `${basePath}?${s}` : basePath;
}

export function countActiveFilters(params: CatalogParams): number {
  let n = 0;
  for (const key of [
    "product_type",
    "type",
    "min_price",
    "max_price",
    "indoor_outdoor",
    "sunlight",
    "water_requirement",
    "difficulty_level",
    "availability",
    "min_rating",
  ] as const) {
    if (params[key]) n += 1;
  }
  return n;
}
