"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  cancelTransfer,
  completeTransfer,
  createTransfer,
  fetchTransfers,
  shipTransfer,
  type StockTransfer,
} from "@/lib/api/inventory";
import { fetchWarehouses, type AdminWarehouse } from "@/lib/api/warehouses";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function TransfersPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, ["inventory.view", "inventory.transfer"]);
  const canTransfer = hasPermission(user, ["inventory.transfer", "inventory.adjust"]);
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<StockTransfer[]>([]);
  const [warehouses, setWarehouses] = useState<AdminWarehouse[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState<{ id: number; action: "ship" | "complete" | "cancel" } | null>(null);
  const [busy, setBusy] = useState(false);

  const [fromId, setFromId] = useState("");
  const [toId, setToId] = useState("");
  const [productId, setProductId] = useState("");
  const [qty, setQty] = useState("1");
  const [notes, setNotes] = useState("");

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: inventory.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [t, w] = await Promise.all([fetchTransfers({ per_page: 50 }), fetchWarehouses()]);
      setRows(t.data);
      setWarehouses(w.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onCreate(e: FormEvent) {
    e.preventDefault();
    if (!canTransfer) return;
    try {
      await createTransfer({
        from_warehouse_id: Number(fromId),
        to_warehouse_id: Number(toId),
        notes: notes || undefined,
        items: [{ product_id: Number(productId), quantity: Number(qty) }],
      });
      push("Transfer created as draft", "success");
      setProductId("");
      setQty("1");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Create failed", "error");
    }
  }

  async function runPending() {
    if (!pending) return;
    setBusy(true);
    try {
      if (pending.action === "ship") await shipTransfer(pending.id);
      if (pending.action === "complete") await completeTransfer(pending.id);
      if (pending.action === "cancel") await cancelTransfer(pending.id);
      push(`Transfer ${pending.action} ok`, "success");
      setPending(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Action failed", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Inventory", href: "/inventory" },
          { label: "Transfers" },
        ]}
      />
      <PageHeader
        title="Warehouse transfers"
        description="Stock moves only on Complete (atomic transfer_out + transfer_in). Draft → In transit → Completed."
      />

      <PermissionGate permission={["inventory.transfer", "inventory.adjust"]}>
        <form onSubmit={onCreate} className="mb-6 max-w-2xl">
          <FormSection title="Create transfer">
            <div className="grid gap-3 sm:grid-cols-2">
              <Select required value={fromId} onChange={(e) => setFromId(e.target.value)}>
                <option value="">From warehouse</option>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>{w.code} — {w.name}</option>
                ))}
              </Select>
              <Select required value={toId} onChange={(e) => setToId(e.target.value)}>
                <option value="">To warehouse</option>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>{w.code} — {w.name}</option>
                ))}
              </Select>
              <Input required placeholder="Product ID" value={productId} onChange={(e) => setProductId(e.target.value)} />
              <Input required type="number" min={1} value={qty} onChange={(e) => setQty(e.target.value)} />
              <TextArea className="sm:col-span-2" rows={2} placeholder="Notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
            </div>
            <Button type="submit" className="mt-3">Create draft</Button>
          </FormSection>
        </form>
      </PermissionGate>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No transfers" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded border border-[var(--admin-border)]">
          <table className="min-w-full text-left text-sm">
            <thead className="text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Transfer</th>
                <th className="px-3 py-2">From → To</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Items</th>
                <th className="px-3 py-2">Created</th>
                <th className="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((t) => (
                <tr key={t.id} className="border-t border-[var(--admin-border)]/60">
                  <td className="px-3 py-2 font-medium">{t.transfer_number}</td>
                  <td className="px-3 py-2">{t.from_warehouse_code} → {t.to_warehouse_code}</td>
                  <td className="px-3 py-2"><Badge tone={t.status === "completed" ? "success" : "neutral"}>{t.status}</Badge></td>
                  <td className="px-3 py-2">{t.item_count}</td>
                  <td className="px-3 py-2 text-xs">{formatDateTime(t.created_at)}</td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-2">
                      {t.actions?.can_ship ? (
                        <button type="button" className="text-[var(--admin-primary)] hover:underline" onClick={() => setPending({ id: t.id, action: "ship" })}>Ship</button>
                      ) : null}
                      {t.actions?.can_complete ? (
                        <button type="button" className="text-[var(--admin-primary)] hover:underline" onClick={() => setPending({ id: t.id, action: "complete" })}>Complete</button>
                      ) : null}
                      {t.actions?.can_cancel ? (
                        <button type="button" className="text-red-600 hover:underline" onClick={() => setPending({ id: t.id, action: "cancel" })}>Cancel</button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      <ConfirmDialog
        open={Boolean(pending)}
        title={`${pending?.action ?? ""} transfer?`}
        description="Complete moves stock atomically. Cancel only before complete."
        loading={busy}
        danger={pending?.action === "cancel"}
        confirmLabel="Confirm"
        onCancel={() => setPending(null)}
        onConfirm={() => void runPending()}
      />
    </div>
  );
}
