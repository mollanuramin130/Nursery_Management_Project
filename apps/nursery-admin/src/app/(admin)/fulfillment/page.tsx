"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { ApiError } from "@/lib/api/client";
import { fetchFulfillmentDashboard, type FulfillmentDashboard } from "@/lib/api/fulfillment";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

const CARDS: Array<{ key: keyof FulfillmentDashboard; label: string; href: string }> = [
  { key: "awaiting_picking", label: "Awaiting picking", href: "/fulfillment/picking?status=CONFIRMED" },
  { key: "being_picked", label: "Being picked", href: "/fulfillment/picking" },
  { key: "awaiting_packing", label: "Awaiting packing", href: "/fulfillment/packing" },
  { key: "ready_to_ship", label: "Ready to ship", href: "/fulfillment/shipments?ready=1" },
  { key: "in_transit", label: "In transit", href: "/fulfillment/shipments?status=shipped" },
  { key: "out_for_delivery", label: "Out for delivery", href: "/fulfillment/shipments?status=out_for_delivery" },
  { key: "delivery_exceptions", label: "Delivery exceptions", href: "/fulfillment/exceptions" },
  { key: "delivered_today", label: "Delivered today", href: "/fulfillment/shipments?status=delivered" },
];

export default function FulfillmentDashboardPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "fulfillment.view");
  const [data, setData] = useState<FulfillmentDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: fulfillment.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchFulfillmentDashboard();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Fulfillment" }]} />
      <PageHeader
        title="Fulfillment"
        description="Operational queues on the existing order lifecycle — pick → pack → ship → deliver."
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && data ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {CARDS.map((card) => (
              <Link
                key={card.key}
                href={card.href}
                className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 hover:border-[var(--admin-primary)]"
              >
                <div className="text-xs uppercase tracking-wide text-[var(--admin-muted)]">{card.label}</div>
                <div className="mt-2 text-3xl font-semibold tabular-nums">{data[card.key] as number}</div>
              </Link>
            ))}
          </div>
          {data.warehouse_rule ? (
            <p className="mt-4 text-sm text-[var(--admin-muted)]">{data.warehouse_rule}</p>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
