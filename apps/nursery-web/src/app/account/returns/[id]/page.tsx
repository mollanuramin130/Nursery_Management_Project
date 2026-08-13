"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { apiGet } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Badge } from "@/components/ui/Badge";
import { Skeleton } from "@/components/ui/Skeleton";
type ReturnItem = {
  product_name?: string | null;
  quantity?: number | null;
  reason?: string | null;
};

type ReturnDetail = {
  id: number;
  order_id: number;
  order_number?: string | null;
  status: string;
  notes?: string | null;
  created_at?: string | null;
  items?: ReturnItem[];
};

/** QA-28 — Customer Web return detail (`GET /returns/{id}`) for notification deep links. */
export default function ReturnDetailPage() {
  const params = useParams<{ id: string }>();
  const returnId = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [row, setRow] = useState<ReturnDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    if (!Number.isFinite(returnId) || returnId <= 0) {
      setError("Invalid return");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet<ReturnDetail>(`/returns/${returnId}`);
      setRow(res.data ?? null);
    } catch (e) {
      setRow(null);
      setError(e instanceof Error ? e.message : "Unable to load return");
    } finally {
      setLoading(false);
    }
  }, [returnId]);

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
        title="Return details"
        description="Sign in to view this return request."
        actionHref={loginHref(`/account/returns/${returnId}`)}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-2xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="section-head mb-0">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">
              Return #{Number.isFinite(returnId) ? returnId : "—"}
            </h1>
            <p>Track the status of this return request.</p>
          </div>
          <Link
            href="/account/returns"
            className="text-sm font-semibold text-[var(--color-primary)] hover:underline"
          >
            All returns
          </Link>
        </div>

        {loading ? (
          <Skeleton className="h-32 w-full" />
        ) : error || !row ? (
          <EmptyState
            title="Unable to load return"
            description={error ?? "Return not found."}
            actionHref="/account/returns"
            actionLabel="Back to returns"
          />
        ) : (
          <article className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-sm text-[var(--color-muted)]">
                  Order{" "}
                  <Link
                    href={`/account/orders/${row.order_id}`}
                    className="font-medium text-[var(--color-primary)] hover:underline"
                  >
                    {row.order_number ?? `#${row.order_id}`}
                  </Link>
                </p>
                {row.created_at ? (
                  <p className="mt-1 text-sm text-[var(--color-muted)]">
                    Requested{" "}
                    {new Date(row.created_at).toLocaleDateString("en-IN", {
                      day: "numeric",
                      month: "short",
                      year: "numeric",
                    })}
                  </p>
                ) : null}
              </div>
              <Badge tone="brand">{row.status.replace(/_/g, " ")}</Badge>
            </div>
            {row.notes ? (
              <div>
                <p className="text-sm font-semibold">Notes</p>
                <p className="text-sm text-[var(--color-muted)]">{row.notes}</p>
              </div>
            ) : null}
            <div>
              <p className="mb-2 text-sm font-semibold">Items</p>
              {!row.items?.length ? (
                <p className="text-sm text-[var(--color-muted)]">No item details</p>
              ) : (
                <ul className="space-y-2">
                  {row.items.map((item, idx) => (
                    <li
                      key={`${item.product_name ?? "item"}-${idx}`}
                      className="flex justify-between gap-3 text-sm"
                    >
                      <span>
                        {item.product_name ?? "Item"}
                        {item.reason ? (
                          <span className="block text-[var(--color-muted)]">
                            {item.reason}
                          </span>
                        ) : null}
                      </span>
                      <span className="shrink-0 text-[var(--color-muted)]">
                        Qty {item.quantity ?? "—"}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </article>
        )}
      </div>
    </section>
  );
}
