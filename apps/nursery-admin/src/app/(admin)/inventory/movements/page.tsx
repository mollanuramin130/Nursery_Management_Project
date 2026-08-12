"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchStockMovements, type StockMovement } from "@/lib/api/inventory";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

export default function InventoryMovementsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "inventory.view");
  const [type, setType] = useState("");
  const [productId, setProductId] = useState("");
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<StockMovement[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: inventory.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchStockMovements({
        type: type || undefined,
        product_id: productId ? Number(productId) : undefined,
        page,
        per_page: 30,
      });
      setRows(res.data);
      const pag = (res.meta as { pagination?: { last_page?: number; total?: number } })?.pagination;
      setLastPage(pag?.last_page ?? 1);
      setTotal(pag?.total ?? res.data.length);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, type, productId, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Inventory", href: "/inventory" },
          { label: "Movements" },
        ]}
      />
      <PageHeader
        title="Stock movements"
        description="Immutable ledger. Corrections require a new compensating adjustment — never edit history."
      />

      <div className="mb-4 flex flex-wrap gap-2">
        <Select value={type} onChange={(e) => { setPage(1); setType(e.target.value); }}>
          <option value="">All types</option>
          {["sale", "reserve", "release", "purchase_in", "return_in", "damage", "loss", "adjust_in", "adjust_out", "transfer_in", "transfer_out"].map((t) => (
            <option key={t} value={t}>{t}</option>
          ))}
        </Select>
        <Input
          placeholder="Product ID"
          value={productId}
          onChange={(e) => { setPage(1); setProductId(e.target.value); }}
          className="w-36"
        />
        <Button variant="secondary" onClick={() => void load()}>Refresh</Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No movements" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded border border-[var(--admin-border)]">
            <table className="min-w-full text-left text-sm">
              <thead className="text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2">When</th>
                  <th className="px-3 py-2">Type</th>
                  <th className="px-3 py-2">Qty</th>
                  <th className="px-3 py-2">Before</th>
                  <th className="px-3 py-2">After</th>
                  <th className="px-3 py-2">Product</th>
                  <th className="px-3 py-2">Ref</th>
                  <th className="px-3 py-2">Actor</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id} className="border-t border-[var(--admin-border)]/60">
                    <td className="px-3 py-2 text-xs">{formatDateTime(m.created_at)}</td>
                    <td className="px-3 py-2"><Badge tone="neutral">{m.type}</Badge></td>
                    <td className="px-3 py-2 font-medium">{m.qty_delta > 0 ? `+${m.qty_delta}` : m.qty_delta}</td>
                    <td className="px-3 py-2">{m.qty_before ?? "—"}</td>
                    <td className="px-3 py-2">{m.qty_after ?? "—"}</td>
                    <td className="px-3 py-2">
                      <Link href={`/inventory/${m.inventory_item_id}`} className="text-[var(--admin-primary)] hover:underline">
                        #{m.product_id}
                      </Link>
                    </td>
                    <td className="px-3 py-2 text-xs">{m.reference_type ?? "—"} {m.reference_id ?? ""}</td>
                    <td className="px-3 py-2 text-xs">{m.actor_user_id ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <span className="text-xs text-[var(--admin-muted)]">Page {page}/{lastPage} · {total}</span>
            <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </>
      ) : null}
    </div>
  );
}
