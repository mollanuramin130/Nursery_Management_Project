const KEY = "gl_recently_viewed";
const MAX = 12;

export type RecentProduct = {
  id: number;
  slug: string;
  name: string;
  thumbnail_url?: string | null;
  price?: number;
  currency?: string;
};

export function getRecentlyViewed(): RecentProduct[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as RecentProduct[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function trackRecentlyViewed(product: RecentProduct): void {
  if (typeof window === "undefined") return;
  const next = [
    product,
    ...getRecentlyViewed().filter((p) => p.id !== product.id && p.slug !== product.slug),
  ].slice(0, MAX);
  localStorage.setItem(KEY, JSON.stringify(next));

  // Best-effort server sync (auth or guest token) — never blocks UX.
  void import("@/lib/api")
    .then(({ api }) =>
      api.post("/product-views", { product_id: product.id }).catch(() => undefined),
    )
    .catch(() => undefined);
}
