"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  fetchInventoryItem,
  updateInventoryThreshold,
  type InventoryItemRow,
  type StockMovement,
} from "@/lib/api/inventory";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function InventoryItemDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "inventory.view");
  const push = useToastStore((s) => s.push);
  const [row, setRow] = useState<(InventoryItemRow & { recent_movements?: StockMovement[] }) | null>(null);
  const [threshold, setThreshold] = useState("");
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
      const res = await fetchInventoryItem(id);
      setRow(res.data);
      setThreshold(String(res.data.low_stock_threshold ?? 0));
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function saveThreshold() {
    try {
      await updateInventoryThreshold(id, Number(threshold));
      push("Threshold updated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Inventory", href: "/inventory" },
          { label: row?.sku ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={row?.product_name ?? "Inventory item"}
        description={row ? `${row.sku} · ${row.warehouse_code}` : undefined}
        actions={
          <Link href="/inventory">
            <Button variant="secondary">Back to list</Button>
          </Link>
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && row ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <FormSection title="Stock">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div><dt className="text-xs text-[var(--admin-muted)]">On hand</dt><dd className="text-lg font-semibold">{row.qty_on_hand}</dd></div>
              <div><dt className="text-xs text-[var(--admin-muted)]">Reserved</dt><dd className="text-lg font-semibold">{row.qty_reserved}</dd></div>
              <div><dt className="text-xs text-[var(--admin-muted)]">Damaged</dt><dd>{row.qty_damaged ?? 0}</dd></div>
              <div><dt className="text-xs text-[var(--admin-muted)]">Available</dt><dd className="text-lg font-semibold">{row.sellable ?? row.quantity_available}</dd></div>
              <div><dt className="text-xs text-[var(--admin-muted)]">Status</dt><dd><Badge tone={row.stock_status === "OUT_OF_STOCK" ? "danger" : row.stock_status === "LOW_STOCK" ? "warning" : "success"}>{row.stock_status}</Badge></dd></div>
              <div><dt className="text-xs text-[var(--admin-muted)]">Warehouse</dt><dd>{row.warehouse_name ?? row.warehouse_code}</dd></div>
            </dl>
            <PermissionGate permission="inventory.adjust">
              <div className="mt-3 flex flex-wrap items-end gap-2">
                <Input label="Low-stock threshold" value={threshold} onChange={(e) => setThreshold(e.target.value)} className="w-40" />
                <Button onClick={() => void saveThreshold()}>Save threshold</Button>
              </div>
            </PermissionGate>
          </FormSection>

          <FormSection title="Recent movements">
            {(row.recent_movements ?? []).length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No movements yet.</p>
            ) : (
              <ul className="space-y-2 text-sm">
                {row.recent_movements!.map((m) => (
                  <li key={m.id} className="flex justify-between gap-2 border-b border-[var(--admin-border)]/50 pb-1">
                    <span><Badge tone="neutral">{m.type}</Badge> {m.qty_delta > 0 ? `+${m.qty_delta}` : m.qty_delta}</span>
                    <span className="text-xs text-[var(--admin-muted)]">{formatDateTime(m.created_at)}</span>
                  </li>
                ))}
              </ul>
            )}
            <Link href={`/inventory/movements`} className="mt-2 inline-block text-sm text-[var(--admin-primary)] hover:underline">
              Open full ledger
            </Link>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
