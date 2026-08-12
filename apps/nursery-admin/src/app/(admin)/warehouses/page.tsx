"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  createWarehouse,
  fetchWarehouses,
  updateWarehouse,
  type AdminWarehouse,
} from "@/lib/api/warehouses";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

const emptyForm = {
  code: "",
  name: "",
  city: "",
  is_default: false,
  status: "active",
};

export default function WarehousesPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "inventory.view");
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);

  const [rows, setRows] = useState<AdminWarehouse[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<AdminWarehouse | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState(emptyForm);
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
      const res = await fetchWarehouses();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setCreating(true);
    setForm(emptyForm);
  }

  function openEdit(row: AdminWarehouse) {
    setCreating(false);
    setEditing(row);
    setForm({
      code: row.code,
      name: row.name,
      city: row.city ?? "",
      is_default: row.is_default,
      status: row.status || "active",
    });
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!canAdjust) return;
    setSaving(true);
    try {
      const payload = {
        code: form.code.trim(),
        name: form.name.trim(),
        city: form.city.trim() || null,
        is_default: form.is_default,
        status: form.status,
      };
      if (editing) {
        await updateWarehouse(editing.id, payload);
        push("Warehouse updated", "success");
      } else {
        await createWarehouse(payload);
        push("Warehouse created", "success");
      }
      setCreating(false);
      setEditing(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
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
          { label: "Warehouses" },
        ]}
      />
      <PageHeader
        title="Warehouses"
        description="Physical stock locations. Inactive warehouses cannot receive purchase stock."
        actions={
          canAdjust ? (
            <Button type="button" onClick={openCreate}>
              New warehouse
            </Button>
          ) : undefined
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <div className="grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
          <div>
            {rows.length === 0 ? (
              <EmptyState title="No warehouses" />
            ) : (
              <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-3 py-2.5">Name</th>
                      <th className="px-3 py-2.5">Code</th>
                      <th className="px-3 py-2.5">City</th>
                      <th className="px-3 py-2.5">SKUs</th>
                      <th className="px-3 py-2.5">Status</th>
                      <th className="px-3 py-2.5">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row) => (
                      <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-3 py-2.5">
                          {row.name}
                          {row.is_default ? (
                            <Badge tone="success" className="ml-2">
                              Default
                            </Badge>
                          ) : null}
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs">{row.code}</td>
                        <td className="px-3 py-2.5">{row.city || "—"}</td>
                        <td className="px-3 py-2.5">{row.inventory_sku_count ?? 0}</td>
                        <td className="px-3 py-2.5">
                          <Badge tone={row.status === "active" ? "success" : "warning"}>
                            {row.status}
                          </Badge>
                        </td>
                        <td className="px-3 py-2.5">
                          {canAdjust ? (
                            <button
                              type="button"
                              className="text-[var(--admin-primary)] hover:underline"
                              onClick={() => openEdit(row)}
                            >
                              Edit
                            </button>
                          ) : (
                            "—"
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {(creating || editing) && canAdjust ? (
            <FormSection
              title={editing ? `Edit ${editing.code}` : "New warehouse"}
              description="Zones/racks are deferred; keep warehouses operationally simple."
            >
              <form className="space-y-3" onSubmit={onSubmit}>
                <Input
                  label="Code"
                  value={form.code}
                  onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                  required
                />
                <Input
                  label="Name"
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  required
                />
                <Input
                  label="City"
                  value={form.city}
                  onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))}
                />
                <Select
                  label="Status"
                  value={form.status}
                  onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </Select>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={form.is_default}
                    onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked }))}
                  />
                  Default warehouse
                </label>
                <div className="flex gap-2">
                  <Button type="submit" disabled={saving}>
                    {saving ? "Saving…" : "Save"}
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => {
                      setCreating(false);
                      setEditing(null);
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            </FormSection>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
