"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchProducts } from "@/lib/api/products";
import { createPurchaseOrder, fetchSuppliers, type AdminSupplier } from "@/lib/api/suppliers";
import { fetchWarehouses, type AdminWarehouse } from "@/lib/api/warehouses";
import { hasPermission } from "@/lib/auth/permissions";
import { formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type Line = {
  product_id: string;
  quantity: string;
  unit_cost: string;
};

export default function NewPurchaseOrderPage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);

  const [suppliers, setSuppliers] = useState<AdminSupplier[]>([]);
  const [warehouses, setWarehouses] = useState<AdminWarehouse[]>([]);
  const [products, setProducts] = useState<Array<{ id: number; name: string; sku: string }>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [supplierId, setSupplierId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [expectedAt, setExpectedAt] = useState("");
  const [taxTotal, setTaxTotal] = useState("0");
  const [status, setStatus] = useState("ordered");
  const [lines, setLines] = useState<Line[]>([{ product_id: "", quantity: "1", unit_cost: "0" }]);

  const load = useCallback(async () => {
    if (!canAdjust) {
      setLoading(false);
      setError("Missing permission: inventory.adjust");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [s, w, p] = await Promise.all([
        fetchSuppliers(),
        fetchWarehouses(),
        fetchProducts({ page: 1, per_page: 100, status: "active" }),
      ]);
      setSuppliers(s.data.filter((x) => x.status === "active"));
      setWarehouses(w.data.filter((x) => x.status === "active"));
      setProducts(
        p.data.map((row) => ({
          id: row.id,
          name: row.name,
          sku: row.sku,
        })),
      );
      const def = w.data.find((x) => x.is_default) ?? w.data[0];
      if (def) setWarehouseId(String(def.id));
      if (s.data[0]) setSupplierId(String(s.data[0].id));
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canAdjust]);

  useEffect(() => {
    void load();
  }, [load]);

  const subtotal = useMemo(
    () =>
      lines.reduce((sum, line) => {
        const q = Number(line.quantity) || 0;
        const c = Number(line.unit_cost) || 0;
        return sum + q * c;
      }, 0),
    [lines],
  );

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const items = lines
        .filter((l) => l.product_id && Number(l.quantity) > 0)
        .map((l) => ({
          product_id: Number(l.product_id),
          quantity: Math.floor(Number(l.quantity)),
          unit_cost: Number(l.unit_cost),
        }));
      if (!items.length) throw new Error("Add at least one product line");
      const res = await createPurchaseOrder({
        supplier_id: Number(supplierId),
        warehouse_id: Number(warehouseId),
        tax_total: Number(taxTotal) || 0,
        expected_at: expectedAt || undefined,
        status,
        items,
      });
      push("Purchase order created", "success");
      router.push(`/purchase-orders/${res.data.id}`);
    } catch (err) {
      push(err instanceof Error ? err.message : "Create failed", "error");
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingBlock />;
  if (error) return <ErrorState message={error} onRetry={() => void load()} />;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Purchase Orders", href: "/purchase-orders" },
          { label: "New" },
        ]}
      />
      <PageHeader title="New purchase order" description="Supplier cost is never exposed to customers." />

      <form className="grid max-w-3xl gap-4" onSubmit={onSubmit}>
        <FormSection title="Order information">
          <div className="grid gap-3 sm:grid-cols-2">
            <Select label="Supplier" value={supplierId} onChange={(e) => setSupplierId(e.target.value)} required>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.code})
                </option>
              ))}
            </Select>
            <Select label="Warehouse" value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} required>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name} ({w.code})
                </option>
              ))}
            </Select>
            <Input
              label="Expected delivery"
              type="date"
              value={expectedAt}
              onChange={(e) => setExpectedAt(e.target.value)}
            />
            <Select label="Initial status" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="draft">Draft</option>
              <option value="ordered">Ordered</option>
              <option value="approved">Approved</option>
            </Select>
            <Input
              label="Tax total"
              type="number"
              min="0"
              step="0.01"
              value={taxTotal}
              onChange={(e) => setTaxTotal(e.target.value)}
            />
          </div>
        </FormSection>

        <FormSection title="Products" description="Unit cost is supplier purchase cost.">
          <div className="space-y-3">
            {lines.map((line, idx) => (
              <div key={idx} className="grid gap-2 rounded border border-[var(--admin-border)] p-3 sm:grid-cols-[2fr_1fr_1fr_auto]">
                <Select
                  label="Product"
                  value={line.product_id}
                  onChange={(e) =>
                    setLines((prev) =>
                      prev.map((l, i) => (i === idx ? { ...l, product_id: e.target.value } : l)),
                    )
                  }
                  required
                >
                  <option value="">Select…</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name} ({p.sku})
                    </option>
                  ))}
                </Select>
                <Input
                  label="Qty"
                  type="number"
                  min="1"
                  value={line.quantity}
                  onChange={(e) =>
                    setLines((prev) =>
                      prev.map((l, i) => (i === idx ? { ...l, quantity: e.target.value } : l)),
                    )
                  }
                  required
                />
                <Input
                  label="Unit cost"
                  type="number"
                  min="0"
                  step="0.01"
                  value={line.unit_cost}
                  onChange={(e) =>
                    setLines((prev) =>
                      prev.map((l, i) => (i === idx ? { ...l, unit_cost: e.target.value } : l)),
                    )
                  }
                  required
                />
                <div className="flex items-end">
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={lines.length <= 1}
                    onClick={() => setLines((prev) => prev.filter((_, i) => i !== idx))}
                  >
                    Remove
                  </Button>
                </div>
              </div>
            ))}
            <Button
              type="button"
              variant="secondary"
              onClick={() => setLines((prev) => [...prev, { product_id: "", quantity: "1", unit_cost: "0" }])}
            >
              Add line
            </Button>
          </div>
        </FormSection>

        <FormSection title="Summary">
          <p className="text-sm">
            Subtotal: <strong>{formatMoney(subtotal)}</strong> · Tax:{" "}
            <strong>{formatMoney(Number(taxTotal) || 0)}</strong> · Grand:{" "}
            <strong>{formatMoney(subtotal + (Number(taxTotal) || 0))}</strong>
          </p>
          <div className="mt-3 flex gap-2">
            <Button type="submit" disabled={saving}>
              {saving ? "Creating…" : "Create purchase order"}
            </Button>
            <Link href="/purchase-orders">
              <Button type="button" variant="secondary">
                Cancel
              </Button>
            </Link>
          </div>
        </FormSection>
      </form>
    </div>
  );
}
