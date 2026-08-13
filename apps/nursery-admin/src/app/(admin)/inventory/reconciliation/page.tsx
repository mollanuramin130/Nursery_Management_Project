"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Input, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchInventory,
  reconcileInventory,
  type InventoryItemRow,
} from "@/lib/api/inventory";
import { hasPermission } from "@/lib/auth/permissions";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type Draft = {
  physical: string;
  reason: string;
};

export default function InventoryReconciliationPage() {
  const user = useAuthStore((s) => s.user);
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);

  const [rows, setRows] = useState<InventoryItemRow[]>([]);
  const [drafts, setDrafts] = useState<Record<number, Draft>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);

  const load = useCallback(async () => {
    if (!canAdjust) {
      setLoading(false);
      setError("Missing permission: inventory.adjust");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchInventory({ q: debouncedQ || undefined });
      setRows(res.data);
      setDrafts((prev) => {
        const next = { ...prev };
        for (const row of res.data) {
          if (!next[row.id]) {
            next[row.id] = { physical: String(row.qty_on_hand), reason: "" };
          }
        }
        return next;
      });
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canAdjust, debouncedQ]);

  useEffect(() => {
    void load();
  }, [load]);

  const pendingRow = rows.find((r) => r.id === pendingId) ?? null;
  const pendingDraft = pendingId != null ? drafts[pendingId] : null;
  const pendingDiff =
    pendingRow && pendingDraft
      ? Math.floor(Number(pendingDraft.physical) || 0) - pendingRow.qty_on_hand
      : 0;

  async function submitReconcile() {
    if (!pendingRow || !pendingDraft) return;
    if (!pendingDraft.reason.trim()) {
      push("Reason is required", "error");
      return;
    }
    setSaving(true);
    try {
      await reconcileInventory({
        inventory_item_id: pendingRow.id,
        physical_qty: Math.max(0, Math.floor(Number(pendingDraft.physical) || 0)),
        reason: pendingDraft.reason.trim(),
      });
      push("Reconciliation posted as stock movement", "success");
      setPendingId(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Reconcile failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Inventory", href: "/inventory" },
          { label: "Reconciliation" },
        ]}
      />
      <PageHeader
        title="Inventory reconciliation"
        description="Physical count vs system on-hand. Differences create adjust_in / adjust_out movements — never silent overwrite."
        actions={
          <Link href="/inventory">
            <Button type="button" variant="secondary">
              Back to inventory
            </Button>
          </Link>
        }
      />

      <form
        className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3"
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          void load();
        }}
      >
        <Input
          label="Search"
          placeholder="Product or SKU"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="min-w-[220px]"
        />
        <Button type="submit">Search</Button>
      </form>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No inventory rows" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Product</th>
                  <th className="px-3 py-2.5">SKU</th>
                  <th className="px-3 py-2.5">Warehouse</th>
                  <th className="px-3 py-2.5">System qty</th>
                  <th className="px-3 py-2.5">Physical qty</th>
                  <th className="px-3 py-2.5">Difference</th>
                  <th className="px-3 py-2.5">Reason</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => {
                  const draft = drafts[row.id] ?? { physical: String(row.qty_on_hand), reason: "" };
                  const physical = Math.floor(Number(draft.physical) || 0);
                  const diff = physical - row.qty_on_hand;
                  return (
                    <tr key={row.id} className="border-b border-[var(--admin-border)]/70 align-top">
                      <td className="px-3 py-2.5 font-medium">{row.product_name}</td>
                      <td className="px-3 py-2.5 font-mono text-xs">{row.sku}</td>
                      <td className="px-3 py-2.5">{row.warehouse_code ?? row.warehouse_id}</td>
                      <td className="px-3 py-2.5">{row.qty_on_hand}</td>
                      <td className="px-3 py-2.5">
                        <Input
                          type="number"
                          min="0"
                          value={draft.physical}
                          onChange={(e) =>
                            setDrafts((prev) => ({
                              ...prev,
                              [row.id]: { ...draft, physical: e.target.value },
                            }))
                          }
                        />
                      </td>
                      <td className="px-3 py-2.5 font-medium">
                        {diff > 0 ? `+${diff}` : diff}
                      </td>
                      <td className="px-3 py-2.5 min-w-[180px]">
                        <TextArea
                          value={draft.reason}
                          onChange={(e) =>
                            setDrafts((prev) => ({
                              ...prev,
                              [row.id]: { ...draft, reason: e.target.value },
                            }))
                          }
                          rows={2}
                        />
                      </td>
                      <td className="px-3 py-2.5">
                        <Button
                          type="button"
                          size="sm"
                          disabled={diff === 0}
                          onClick={() => setPendingId(row.id)}
                        >
                          Post
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )
      ) : null}

      <ConfirmDialog
        open={pendingId != null}
        title="Confirm reconciliation"
        description={
          pendingRow
            ? `System ${pendingRow.qty_on_hand} → physical ${pendingDraft?.physical ?? "?"} (delta ${pendingDiff}). Creates a corrective stock movement.`
            : ""
        }
        confirmLabel="Post adjustment"
        loading={saving}
        onCancel={() => setPendingId(null)}
        onConfirm={() => void submitReconcile()}
      />
    </div>
  );
}
