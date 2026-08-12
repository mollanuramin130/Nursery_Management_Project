"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiGet } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Badge } from "@/components/ui/Badge";
import { Skeleton } from "@/components/ui/Skeleton";

type ReturnRow = {
  id: number;
  order_id: number;
  status: string;
  notes?: string | null;
  created_at?: string | null;
};

export default function ReturnsPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [items, setItems] = useState<ReturnRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiGet<ReturnRow[]>("/customer/returns", { per_page: 30 });
      setItems(Array.isArray(res.data) ? res.data : []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (bootstrapped && user) void load();
  }, [bootstrapped, user, load]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-2xl">
          <Skeleton className="h-24 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Returns"
        description="Sign in to view return requests."
        actionHref={loginHref("/account/returns")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-2xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Returns</h1>
          <p>Track return requests for delivered orders.</p>
        </div>
        {loading ? (
          <Skeleton className="h-24 w-full" />
        ) : !items.length ? (
          <EmptyState
            title="No return requests"
            description="When you return an item from a delivered order, it will show up here."
            actionHref="/account/orders"
            actionLabel="View orders"
          />
        ) : (
          <ul className="space-y-3">
            {items.map((r) => (
              <li
                key={r.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
              >
                <div>
                  <p className="font-semibold">Return #{r.id}</p>
                  <p className="text-sm text-[var(--color-muted)]">
                    Order{" "}
                    <Link
                      href={`/account/orders/${r.order_id}`}
                      className="text-[var(--color-primary)] hover:underline"
                    >
                      #{r.order_id}
                    </Link>
                    {r.created_at
                      ? ` · ${new Date(r.created_at).toLocaleDateString("en-IN")}`
                      : ""}
                  </p>
                </div>
                <Badge tone="brand">{r.status.replace(/_/g, " ")}</Badge>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}
