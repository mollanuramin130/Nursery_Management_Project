"use client";

import Image from "next/image";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { money } from "@/lib/format";
import type { Cart, OrderSummary } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { Badge } from "@/components/ui/Badge";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Skeleton } from "@/components/ui/Skeleton";
import { cn } from "@/lib/cn";

const FILTERS = [
  { id: "", label: "All" },
  { id: "active", label: "Active" },
  { id: "CONFIRMED", label: "Confirmed" },
  { id: "SHIPPED", label: "Shipped" },
  { id: "DELIVERED", label: "Delivered" },
  { id: "CANCELLED", label: "Cancelled" },
] as const;

function statusTone(status: string): "brand" | "success" | "warning" | "error" {
  if (status === "DELIVERED") return "success";
  if (status === "CANCELLED" || status === "PAYMENT_FAILED") return "error";
  if (status === "PENDING_PAYMENT") return "warning";
  return "brand";
}

function formatDate(iso?: string | null) {
  if (!iso) return "";
  return new Date(iso).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export function OrdersClient() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const placed = useSearchParams().get("placed");
  const toast = useToastStore((s) => s.push);
  const [orders, setOrders] = useState<OrderSummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState("");
  const [q, setQ] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet<OrderSummary[]>("/orders", {
        status: status || undefined,
        q: search || undefined,
        page,
        per_page: 10,
      });
      setOrders(Array.isArray(res.data) ? res.data : []);
      setLastPage(res.meta?.pagination?.last_page ?? 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed");
      setOrders([]);
    } finally {
      setLoading(false);
    }
  }, [status, search, page]);

  useEffect(() => {
    if (!bootstrapped || !user) {
      queueMicrotask(() => setLoading(false));
      return;
    }
    void load();
  }, [bootstrapped, user, load]);

  async function onReorder(orderId: number) {
    if (busyId) return;
    setBusyId(orderId);
    try {
      const res = await apiSend<{
        cart: Cart;
        reorder_summary: {
          added: unknown[];
          unavailable: unknown[];
          price_changed: unknown[];
        };
      }>("post", `/orders/${orderId}/reorder`);
      useCartStore.setState({ cart: res.data.cart });
      const s = res.data.reorder_summary;
      const parts = [
        `${s.added.length} added`,
        s.unavailable.length ? `${s.unavailable.length} unavailable` : null,
        s.price_changed.length ? `${s.price_changed.length} price changed` : null,
      ].filter(Boolean);
      toast(`Reorder: ${parts.join(" · ")}`);
    } catch (e) {
      toast(e instanceof Error ? e.message : "Reorder failed", "error");
    } finally {
      setBusyId(null);
    }
  }

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-3xl space-y-3">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-24 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Sign in to view orders"
        description="Track deliveries and reorder favorites."
        actionHref={loginHref("/account/orders")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-3xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">My Orders</h1>
          <p>Track deliveries, cancel when allowed, and reorder favorites.</p>
        </div>

        {placed ? (
          <div className="mb-6 rounded-[var(--radius-lg)] border border-[var(--color-success)] bg-[var(--color-success-soft)] p-5">
            <p className="font-semibold text-[var(--color-success)]">Order placed successfully</p>
            <p className="mt-1 text-sm">Order {placed}</p>
          </div>
        ) : null}

        <form
          className="mb-4 flex flex-wrap gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            setPage(1);
            setSearch(q.trim());
          }}
        >
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search order number"
            className="min-w-[220px] flex-1"
          />
          <Button type="submit" variant="secondary" size="sm">
            Search
          </Button>
        </form>

        <div className="mb-5 flex flex-wrap gap-2">
          {FILTERS.map((f) => (
            <button
              key={f.id || "all"}
              type="button"
              onClick={() => {
                setStatus(f.id);
                setPage(1);
              }}
              className={cn(
                "rounded-full border px-3 py-1.5 text-sm font-semibold",
                status === f.id
                  ? "border-[var(--color-primary)] bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]"
                  : "border-[var(--color-border)] text-[var(--color-muted)]",
              )}
            >
              {f.label}
            </button>
          ))}
        </div>

        {error ? <p className="mb-4 text-sm text-[var(--color-error)]">{error}</p> : null}

        {loading ? (
          <div className="space-y-3">
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
          </div>
        ) : !orders.length && !error ? (
          <EmptyState
            title="No orders yet"
            description="Your next plant adventure starts here."
            actionHref="/shop"
            actionLabel="Explore plants"
          />
        ) : (
          <div className="space-y-3">
            {orders.map((o) => (
              <article
                key={o.id}
                className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{o.order_number}</p>
                    <p className="mt-0.5 text-xs text-[var(--color-muted)]">
                      Placed {formatDate(o.placed_at ?? o.created_at)}
                    </p>
                  </div>
                  <Badge tone={statusTone(o.status)}>{o.status.replaceAll("_", " ")}</Badge>
                </div>
                <div className="mt-3 flex gap-3">
                  <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                    {o.thumbnail ? (
                      <Image src={o.thumbnail} alt="" fill className="object-cover" sizes="64px" />
                    ) : null}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{o.preview_name ?? "Order items"}</p>
                    <p className="text-sm text-[var(--color-muted)]">
                      {o.item_count ?? 0} item{(o.item_count ?? 0) === 1 ? "" : "s"}
                    </p>
                    <p className="mt-1 font-bold text-[var(--color-primary-deep)]">
                      {money(o.grand_total, o.currency)}
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                  <Link href={`/account/orders/${o.id}`}>
                    <Button size="sm" variant="secondary">
                      View order
                    </Button>
                  </Link>
                  {o.can_reorder ? (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busyId === o.id}
                      onClick={() => void onReorder(o.id)}
                    >
                      {busyId === o.id ? "…" : "Reorder"}
                    </Button>
                  ) : null}
                </div>
              </article>
            ))}

            {lastPage > 1 ? (
              <div className="flex items-center justify-between pt-2">
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={page <= 1 || loading}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <span className="text-sm text-[var(--color-muted)]">
                  Page {page} of {lastPage}
                </span>
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={page >= lastPage || loading}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            ) : null}
          </div>
        )}
      </div>
    </section>
  );
}
