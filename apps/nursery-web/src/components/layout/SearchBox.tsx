"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { apiGet } from "@/lib/api";
import type { CampaignSummary, Category, ProductSummary } from "@/lib/types";
import { money } from "@/lib/format";
import { cn } from "@/lib/cn";
import {
  nextSearchGeneration,
  shouldApplySearchResult,
} from "@/lib/search-suggestions";

const RECENT_KEY = "gl_recent_searches";
const POPULAR = ["money plant", "snake plant", "indoor", "pots", "beginner"];

function readRecent(): string[] {
  try {
    const raw = localStorage.getItem(RECENT_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as string[];
    return Array.isArray(parsed) ? parsed.slice(0, 6) : [];
  } catch {
    return [];
  }
}

function pushRecent(term: string) {
  const next = [term, ...readRecent().filter((t) => t.toLowerCase() !== term.toLowerCase())].slice(0, 6);
  localStorage.setItem(RECENT_KEY, JSON.stringify(next));
}

export function SearchBox({
  variant = "desktop",
  autoFocus,
  onNavigate,
}: {
  variant?: "desktop" | "mobile";
  autoFocus?: boolean;
  onNavigate?: () => void;
}) {
  const router = useRouter();
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [matchedFromApi, setMatchedFromApi] = useState<
    Array<{ type: string; label: string; slug: string }>
  >([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [campaigns, setCampaigns] = useState<CampaignSummary[]>([]);
  const [recent, setRecent] = useState<string[]>([]);
  const [activeIdx, setActiveIdx] = useState(-1);
  const boxRef = useRef<HTMLDivElement>(null);
  const searchGenRef = useRef(0);

  useEffect(() => {
    queueMicrotask(() => setRecent(readRecent()));
    void apiGet<Category[]>("/categories")
      .then((res) => setCategories((res.data ?? []).filter((c) => !c.parent_id).slice(0, 20)))
      .catch(() => undefined);
    void apiGet<CampaignSummary[]>("/campaigns")
      .then((res) => setCampaigns((res.data ?? []).slice(0, 8)))
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (!boxRef.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  useEffect(() => {
    if (q.trim().length < 2) {
      searchGenRef.current = nextSearchGeneration(searchGenRef.current);
      queueMicrotask(() => {
        setProducts([]);
        setMatchedFromApi([]);
        setLoading(false);
      });
      return;
    }
    const gen = nextSearchGeneration(searchGenRef.current);
    searchGenRef.current = gen;
    queueMicrotask(() => setLoading(true));
    const t = window.setTimeout(() => {
      void apiGet<
        Array<{ type: string; label: string; slug: string; product_type?: string }>
      >("/search/suggestions", { q: q.trim() })
        .then((res) => {
          if (!shouldApplySearchResult(gen, searchGenRef.current)) return;
          const rows = res.data ?? [];
          setMatchedFromApi(rows.filter((r) => r.type === "category").slice(0, 4));
          setProducts(
            rows
              .filter((r) => r.type === "product")
              .slice(0, 5)
              .map(
                (r) =>
                  ({
                    id: 0,
                    sku: "",
                    name: r.label,
                    slug: r.slug,
                    product_type: r.product_type ?? "plant",
                    price: 0,
                    currency: "INR",
                    stock_status: "in_stock",
                  }) satisfies ProductSummary,
              ),
          );
        })
        .catch(() => {
          if (!shouldApplySearchResult(gen, searchGenRef.current)) return;
          setProducts([]);
          setMatchedFromApi([]);
        })
        .finally(() => {
          if (shouldApplySearchResult(gen, searchGenRef.current)) {
            setLoading(false);
          }
        });
    }, 220);
    return () => window.clearTimeout(t);
  }, [q]);

  const matchedCats = useMemo(() => {
    if (matchedFromApi.length) {
      return matchedFromApi.map(
        (r) =>
          ({
            id: 0,
            name: r.label,
            slug: r.slug,
          }) satisfies Category,
      );
    }
    const term = q.trim().toLowerCase();
    if (term.length < 2) return [];
    return categories.filter((c) => c.name.toLowerCase().includes(term)).slice(0, 4);
  }, [q, categories, matchedFromApi]);

  const matchedCampaigns = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (term.length < 2) return [];
    return campaigns
      .filter((c) => c.title.toLowerCase().includes(term) || (c.subtitle ?? "").toLowerCase().includes(term))
      .slice(0, 3);
  }, [q, campaigns]);

  const flatLinks = useMemo(() => {
    const links: Array<{ href: string; label: string }> = [];
    products.forEach((p) => links.push({ href: `/product/${p.slug}`, label: p.name }));
    matchedCats.forEach((c) => links.push({ href: `/category/${c.slug}`, label: c.name }));
    matchedCampaigns.forEach((c) => links.push({ href: `/campaigns/${c.slug}`, label: c.title }));
    return links;
  }, [products, matchedCats, matchedCampaigns]);

  function goSearch(term: string) {
    const query = term.trim();
    if (!query) return;
    pushRecent(query);
    setRecent(readRecent());
    setOpen(false);
    onNavigate?.();
    router.push(`/search?q=${encodeURIComponent(query)}`);
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    goSearch(q);
  }

  function onKeyDown(e: React.KeyboardEvent) {
    if (!open) return;
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setActiveIdx((i) => Math.min(i + 1, flatLinks.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActiveIdx((i) => Math.max(i - 1, -1));
    } else if (e.key === "Enter" && activeIdx >= 0 && flatLinks[activeIdx]) {
      e.preventDefault();
      onNavigate?.();
      router.push(flatLinks[activeIdx].href);
      setOpen(false);
    }
  }

  const showIdle = open && q.trim().length < 2;
  const showResults = open && q.trim().length >= 2;

  return (
    <div ref={boxRef} className={cn("relative", variant === "desktop" ? "w-full" : "w-full")}>
      <form onSubmit={onSubmit} className="relative">
        <label htmlFor={`search-${variant}`} className="sr-only">
          Search plants
        </label>
        <input
          id={`search-${variant}`}
          value={q}
          autoFocus={autoFocus}
          onChange={(e) => {
            setQ(e.target.value);
            setOpen(true);
            setActiveIdx(-1);
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          placeholder="Search plants, pots, fertilizers…"
          className="w-full min-h-11 rounded-full border border-[var(--color-border)] bg-[var(--color-bg)] pl-4 pr-24 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[rgba(26,92,58,0.12)]"
          autoComplete="off"
          role="combobox"
          aria-expanded={open}
          aria-controls={`search-panel-${variant}`}
          aria-autocomplete="list"
        />
        {q ? (
          <button
            type="button"
            className="absolute right-16 top-1/2 -translate-y-1/2 text-sm text-[var(--color-muted)]"
            onClick={() => {
              setQ("");
              setProducts([]);
            }}
          >
            Clear
          </button>
        ) : null}
        <button
          type="submit"
          className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-full bg-[var(--color-primary-deep)] px-4 py-2 text-sm font-semibold text-white"
        >
          Search
        </button>
      </form>

      {(showIdle || showResults) && (
        <div
          id={`search-panel-${variant}`}
          role="listbox"
          className="absolute left-0 right-0 top-[calc(100%+6px)] z-[var(--z-dropdown)] max-h-[70vh] overflow-y-auto rounded-[var(--radius-md)] border border-[var(--color-border)] bg-white shadow-[var(--shadow-md)]"
        >
          {showIdle ? (
            <div className="p-3">
              {recent.length ? (
                <Section title="Recent">
                  {recent.map((term) => (
                    <button
                      key={term}
                      type="button"
                      className="block w-full rounded-[var(--radius-md)] px-3 py-2.5 text-left text-sm hover:bg-[var(--color-primary-soft)]"
                      onClick={() => goSearch(term)}
                    >
                      {term}
                    </button>
                  ))}
                </Section>
              ) : null}
              <Section title="Popular">
                {POPULAR.map((term) => (
                  <button
                    key={term}
                    type="button"
                    className="block w-full rounded-[var(--radius-md)] px-3 py-2.5 text-left text-sm hover:bg-[var(--color-primary-soft)]"
                    onClick={() => goSearch(term)}
                  >
                    {term}
                  </button>
                ))}
              </Section>
            </div>
          ) : null}

          {showResults ? (
            <div className="p-3">
              {loading ? (
                <p className="px-3 py-2 text-sm text-[var(--color-muted)]">Searching…</p>
              ) : null}

              {products.length ? (
                <Section title="Products">
                  {products.map((p, i) => (
                    <Link
                      key={p.slug}
                      href={`/product/${p.slug}`}
                      role="option"
                      aria-selected={activeIdx === i}
                      className={cn(
                        "flex items-center justify-between gap-3 rounded-[var(--radius-md)] px-3 py-2.5 text-sm hover:bg-[var(--color-primary-soft)]",
                        activeIdx === i && "bg-[var(--color-primary-soft)]",
                      )}
                      onClick={() => {
                        pushRecent(q.trim());
                        onNavigate?.();
                        setOpen(false);
                      }}
                    >
                      <span className="font-medium">{p.name}</span>
                      {p.price > 0 ? (
                        <span className="text-[var(--color-muted)]">{money(p.price)}</span>
                      ) : (
                        <span className="text-xs text-[var(--color-muted)]">View</span>
                      )}
                    </Link>
                  ))}
                </Section>
              ) : null}

              {matchedCats.length ? (
                <Section title="Categories">
                  {matchedCats.map((c) => (
                    <Link
                      key={c.id}
                      href={`/category/${c.slug}`}
                      className="block rounded-[var(--radius-md)] px-3 py-2.5 text-sm hover:bg-[var(--color-primary-soft)]"
                      onClick={() => {
                        onNavigate?.();
                        setOpen(false);
                      }}
                    >
                      {c.name}
                    </Link>
                  ))}
                </Section>
              ) : null}

              {matchedCampaigns.length ? (
                <Section title="Campaigns">
                  {matchedCampaigns.map((c) => (
                    <Link
                      key={c.id}
                      href={`/campaigns/${c.slug}`}
                      className="block rounded-[var(--radius-md)] px-3 py-2.5 text-sm hover:bg-[var(--color-primary-soft)]"
                      onClick={() => {
                        onNavigate?.();
                        setOpen(false);
                      }}
                    >
                      {c.title}
                    </Link>
                  ))}
                </Section>
              ) : null}

              {!loading && !products.length && !matchedCats.length && !matchedCampaigns.length ? (
                <p className="px-3 py-4 text-sm text-[var(--color-muted)]">
                  No matches — press Search to browse full results.
                </p>
              ) : null}

              <button
                type="button"
                className="mt-1 w-full rounded-[var(--radius-md)] px-3 py-2.5 text-left text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-primary-soft)]"
                onClick={() => goSearch(q)}
              >
                See all results for “{q.trim()}”
              </button>
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mb-2">
      <p className="px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-[var(--color-muted)]">
        {title}
      </p>
      {children}
    </div>
  );
}
