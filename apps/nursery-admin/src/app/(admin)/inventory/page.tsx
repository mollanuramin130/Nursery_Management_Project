"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Image from "next/image";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import Link from "next/link";
import {
  adjustInventory,
  fetchInventory,
  fetchInventoryDashboard,
  fetchReorderSuggestions,
  fetchStockMovements,
  type InventoryDashboard,
  type InventoryItemRow,
  type ReorderSuggestion,
  type StockMovement,
} from "@/lib/api/inventory";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function stockTone(status?: string) {
  if (status === "OUT_OF_STOCK") return "danger" as const;
  if (status === "LOW_STOCK") return "warning" as const;
  return "success" as const;
}

export default function InventoryPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "inventory.view");
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);

  const [q, setQ] = useState("");
  const [appliedQ, setAppliedQ] = useState("");
  const [lowOnly, setLowOnly] = useState(false);
  const [rows, setRows] = useState<InventoryItemRow[]>([]);
  const [dashboard, setDashboard] = useState<InventoryDashboard | null>(null);
  const [movements, setMovements] = useState<StockMovement[]>([]);
  const [suggestions, setSuggestions] = useState<ReorderSuggestion[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [selected, setSelected] = useState<InventoryItemRow | null>(null);
  const [mode, setMode] = useState<"add" | "remove" | "set">("add");
  const [qty, setQty] = useState("1");
  const [reason, setReason] = useState("purchase");
  const [note, setNote] = useState("");
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: inventory.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [inv, mov, sug, dash] = await Promise.all([
        fetchInventory({
          q: appliedQ || undefined,
          low_stock: lowOnly || undefined,
        }),
        fetchStockMovements({ per_page: 20, page: 1 }),
        fetchReorderSuggestions(),
        fetchInventoryDashboard(),
      ]);
      setRows(inv.data);
      setMovements(mov.data);
      setSuggestions(sug.data.slice(0, 8));
      setDashboard(dash.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, appliedQ, lowOnly]);

  useEffect(() => {
    void load();
  }, [load]);

  const previewDelta = useMemo(() => {
    if (!selected) return 0;
    const n = Number(qty);
    if (!Number.isFinite(n) || n < 0) return 0;
    if (mode === "add") return Math.floor(n);
    if (mode === "remove") return -Math.floor(n);
    return Math.floor(n) - selected.qty_on_hand;
  }, [selected, mode, qty]);

  function openAdjust(row: InventoryItemRow) {
    setSelected(row);
    setMode("add");
    setQty("1");
    setReason("purchase");
    setNote("");
  }

  async function submitAdjust() {
    if (!selected) return;
    setSaving(true);
    try {
      await adjustInventory({
        warehouse_id: selected.warehouse_id,
        product_id: selected.product_id,
        adjustment: previewDelta,
        reason: reason === "damage" ? "damaged" : reason,
        note: note || undefined,
      });
      push("Inventory adjusted", "success");
      setConfirmOpen(false);
      setSelected(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Adjustment failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Inventory" }]} />
      <PageHeader
        title="Inventory"
        description="On-hand − reserved − damaged = available. Adjustments and receiving post stock movements."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href="/inventory/movements">
              <Button type="button" variant="secondary">
                Movements
              </Button>
            </Link>
            <Link href="/inventory/transfers">
              <Button type="button" variant="secondary">
                Transfers
              </Button>
            </Link>
            <Link href="/inventory/reconciliation">
              <Button type="button" variant="secondary">
                Reconciliation
              </Button>
            </Link>
            <Link href="/purchase-orders">
              <Button type="button" variant="secondary">
                Purchase orders
              </Button>
            </Link>
          </div>
        }
      />

      {dashboard ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          {[
            ["SKUs", dashboard.total_skus],
            ["On hand", dashboard.total_on_hand_units],
            ["Reserved", dashboard.total_reserved_units],
            ["Low stock", dashboard.low_stock_count],
            ["Out of stock", dashboard.out_of_stock_count],
            ["Open POs", dashboard.pending_purchase_orders],
            ["Open transfers", dashboard.pending_transfers],
          ].map(([label, value]) => (
            <div
              key={String(label)}
              className="rounded border border-[var(--admin-border)] bg-white px-3 py-2 text-sm"
            >
              <div className="text-xs text-[var(--admin-muted)]">{label}</div>
              <div className="text-lg font-semibold">{value}</div>
            </div>
          ))}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input
          label="Search"
          placeholder="Product or SKU"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="min-w-[220px]"
        />
        <label className="flex items-center gap-2 pb-2 text-sm">
          <input type="checkbox" checked={lowOnly} onChange={(e) => setLowOnly(e.target.checked)} />
          Low stock only
        </label>
        <Button
          onClick={() => {
            setAppliedQ(q);
          }}
        >
          Apply
        </Button>
        <Button variant="secondary" onClick={() => void load()}>
          Refresh
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <div className="grid gap-4 xl:grid-cols-[1.4fr_0.8fr]">
          <div>
            {rows.length === 0 ? (
              <EmptyState title="No inventory rows" />
            ) : (
              <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-3 py-2.5">Product</th>
                      <th className="px-3 py-2.5">SKU</th>
                      <th className="px-3 py-2.5">Warehouse</th>
                      <th className="px-3 py-2.5">On hand</th>
                      <th className="px-3 py-2.5">Reserved</th>
                      <th className="px-3 py-2.5">Available</th>
                      <th className="px-3 py-2.5">Reorder</th>
                      <th className="px-3 py-2.5">Status</th>
                      <th className="px-3 py-2.5">Updated</th>
                      <th className="px-3 py-2.5">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row) => (
                      <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-3 py-2.5">
                          <div className="flex items-center gap-2">
                            <div className="relative h-9 w-9 overflow-hidden rounded bg-[var(--admin-surface-muted)]">
                              {row.thumbnail_url ? (
                                <Image
                                  src={row.thumbnail_url}
                                  alt=""
                                  fill
                                  className="object-cover"
                                  unoptimized
                                />
                              ) : null}
                            </div>
                            <div>
                              <div className="font-medium">
                                <Link href={`/inventory/${row.id}`} className="hover:underline">
                                  {row.product_name}
                                </Link>
                              </div>
                              <div className="text-xs text-[var(--admin-muted)]">
                                {(row.categories ?? []).join(", ") || "—"}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs">{row.sku}</td>
                        <td className="px-3 py-2.5">{row.warehouse_code ?? row.warehouse_id}</td>
                        <td className="px-3 py-2.5">{row.qty_on_hand}</td>
                        <td className="px-3 py-2.5">{row.qty_reserved}</td>
                        <td className="px-3 py-2.5">{row.quantity_available ?? row.sellable}</td>
                        <td className="px-3 py-2.5">{row.reorder_level ?? row.low_stock_threshold}</td>
                        <td className="px-3 py-2.5">
                          <Badge tone={stockTone(row.stock_status)}>
                            {row.stock_status?.replaceAll("_", " ") ?? "—"}
                          </Badge>
                        </td>
                        <td className="px-3 py-2.5 text-xs">{formatDateTime(row.updated_at)}</td>
                        <td className="px-3 py-2.5">
                          <PermissionGate permission="inventory.adjust">
                            <button
                              type="button"
                              className="text-[var(--admin-primary)] hover:underline"
                              onClick={() => openAdjust(row)}
                            >
                              Adjust
                            </button>
                          </PermissionGate>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="space-y-4">
            {selected && canAdjust ? (
              <FormSection title={`Adjust: ${selected.product_name}`} description={`SKU ${selected.sku}`}>
                <form
                  className="space-y-3"
                  onSubmit={(e: FormEvent) => {
                    e.preventDefault();
                    setConfirmOpen(true);
                  }}
                >
                  <div className="text-sm text-[var(--admin-muted)]">
                    On hand: <strong className="text-[var(--admin-ink)]">{selected.qty_on_hand}</strong> ·
                    Sellable: <strong className="text-[var(--admin-ink)]">{selected.sellable}</strong>
                  </div>
                  <Select label="Adjustment type" value={mode} onChange={(e) => setMode(e.target.value as typeof mode)}>
                    <option value="add">Add stock</option>
                    <option value="remove">Remove stock</option>
                    <option value="set">Set absolute on-hand</option>
                  </Select>
                  <Input
                    label="Quantity"
                    type="number"
                    min="0"
                    value={qty}
                    onChange={(e) => setQty(e.target.value)}
                    required
                  />
                  <Select label="Reason" value={reason} onChange={(e) => setReason(e.target.value)}>
                    <option value="purchase">Purchase</option>
                    <option value="damage">Damage</option>
                    <option value="correction">Correction</option>
                    <option value="return">Return</option>
                    <option value="other">Other</option>
                  </Select>
                  <TextArea label="Note" value={note} onChange={(e) => setNote(e.target.value)} />
                  <div className="text-xs text-[var(--admin-muted)]">
                    API delta to send: <strong>{previewDelta}</strong> (absolute set is converted to a
                    delta client-side)
                  </div>
                  <div className="flex gap-2">
                    <Button type="submit" disabled={previewDelta === 0}>
                      Confirm adjustment
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => setSelected(null)}>
                      Cancel
                    </Button>
                  </div>
                </form>
              </FormSection>
            ) : null}

            <FormSection title="Low stock / reorder suggestions" description="Suggestion only — does not auto-create POs.">
              {suggestions.length === 0 ? (
                <p className="text-sm text-[var(--admin-muted)]">No low-stock items.</p>
              ) : (
                <ul className="space-y-2 text-sm">
                  {suggestions.map((s) => (
                    <li key={s.inventory_item_id} className="border-b border-[var(--admin-border)]/60 pb-2">
                      <div className="font-medium">
                        {s.product_name} · {s.stock_status.replaceAll("_", " ")}
                      </div>
                      <div className="text-xs text-[var(--admin-muted)]">
                        Available {s.sellable} / reorder {s.reorder_level} · suggest {s.suggested_reorder_qty}
                        {s.warehouse_code ? ` · ${s.warehouse_code}` : ""}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
              <Link
                href="/purchase-orders/new"
                className="mt-2 inline-block text-sm text-[var(--admin-primary)] hover:underline"
              >
                Create purchase order →
              </Link>
            </FormSection>

            <FormSection title="Recent stock movements" description="Immutable ledger — correct via new movements.">
              {movements.length === 0 ? (
                <p className="text-sm text-[var(--admin-muted)]">No movements yet.</p>
              ) : (
                <ul className="space-y-2 text-sm">
                  {movements.map((m) => (
                    <li key={m.id} className="border-b border-[var(--admin-border)]/60 pb-2">
                      <div className="font-medium">
                        #{m.product_id} · {m.type} · {m.qty_delta > 0 ? "+" : ""}
                        {m.qty_delta}
                      </div>
                      <div className="text-xs text-[var(--admin-muted)]">
                        {formatDateTime(m.created_at)}
                        {m.note ? ` · ${m.note}` : ""}
                        {m.actor_user_id ? ` · admin #${m.actor_user_id}` : ""}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </FormSection>
          </div>
        </div>
      ) : null}

      <ConfirmDialog
        open={confirmOpen}
        title="Confirm inventory adjustment"
        description={`Apply delta ${previewDelta} to on-hand stock for ${selected?.product_name ?? "product"}? Backend remains authoritative.`}
        confirmLabel="Apply"
        loading={saving}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={() => void submitAdjust()}
      />
    </div>
  );
}
