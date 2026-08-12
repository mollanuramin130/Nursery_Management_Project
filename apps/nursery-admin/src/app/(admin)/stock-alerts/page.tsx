"use client";

import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { apiGet } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { ApiError } from "@/lib/api/client";

type Row = {
  id: number;
  status: string;
  product_id: number;
  product_name?: string | null;
  product_sku?: string | null;
  product_stock_status?: string | null;
  user_email?: string | null;
  notified_at?: string | null;
  created_at?: string | null;
};

export default function StockAlertsAdminPage() {
  const user = useAuthStore((s) => s.user);
  const can = hasPermission(user, "inventory.view");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [rows, setRows] = useState<Row[]>([]);

  const load = useCallback(async () => {
    if (!can) {
      setLoading(false);
      setError("Missing permission inventory.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet<Row[]>("/admin/stock-alerts", { status: "all", per_page: 50 });
      setRows(res.data ?? []);
    } catch (e) {
      setError(e instanceof ApiError || e instanceof Error ? e.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [can]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Stock alerts" }]} />
      <PageHeader
        title="Back-in-stock alerts"
        description="Customer subscriptions for out-of-stock products. Notifications fire when sellable stock returns."
      />
      {loading ? <LoadingBlock label="Loading alerts…" /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-[var(--admin-surface-muted,#f8faf8)] text-left text-xs uppercase tracking-wide text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Product</th>
                <th className="px-3 py-2">SKU</th>
                <th className="px-3 py-2">Stock</th>
                <th className="px-3 py-2">Customer</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Updated</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-3 py-6 text-[var(--admin-muted)]">
                    No stock alert subscriptions yet.
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.id} className="border-t border-[var(--admin-border)]">
                    <td className="px-3 py-2 font-medium">{r.product_name ?? r.product_id}</td>
                    <td className="px-3 py-2">{r.product_sku ?? "—"}</td>
                    <td className="px-3 py-2">{r.product_stock_status ?? "—"}</td>
                    <td className="px-3 py-2">{r.user_email ?? "—"}</td>
                    <td className="px-3 py-2">{r.status}</td>
                    <td className="px-3 py-2 text-[var(--admin-muted)]">
                      {r.notified_at ?? r.created_at ?? "—"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
