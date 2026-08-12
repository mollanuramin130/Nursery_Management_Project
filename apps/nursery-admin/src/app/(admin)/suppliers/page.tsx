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
  createSupplier,
  fetchSuppliers,
  updateSupplier,
  type AdminSupplier,
  type SupplierWritePayload,
} from "@/lib/api/suppliers";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

const emptyForm = {
  code: "",
  name: "",
  email: "",
  phone: "",
  city: "",
  state: "",
  contact_person: "",
  gstin: "",
  status: "active",
};

export default function SuppliersPage() {
  const user = useAuthStore((s) => s.user);
  const canAdjust = hasPermission(user, "inventory.adjust");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<AdminSupplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<AdminSupplier | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canAdjust) {
      setLoading(false);
      setError("Missing permission: inventory.adjust");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchSuppliers();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canAdjust]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setCreating(true);
    setForm(emptyForm);
  }

  function openEdit(row: AdminSupplier) {
    setCreating(false);
    setEditing(row);
    setForm({
      code: row.code,
      name: row.name,
      email: row.email ?? "",
      phone: row.phone ?? "",
      city: row.city ?? "",
      state: row.state ?? "",
      contact_person: row.contact_person ?? "",
      gstin: row.gstin ?? "",
      status: row.status,
    });
  }

  function closePanel() {
    setEditing(null);
    setCreating(false);
    setForm(emptyForm);
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: SupplierWritePayload = {
        code: form.code.trim(),
        name: form.name.trim(),
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        city: form.city.trim() || null,
        state: form.state.trim() || null,
        contact_person: form.contact_person.trim() || null,
        gstin: form.gstin.trim() || null,
        status: form.status,
      };
      if (creating) {
        await createSupplier(payload);
        push("Supplier created", "success");
      } else if (editing) {
        await updateSupplier(editing.id, payload);
        push("Supplier updated", "success");
      }
      closePanel();
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  async function toggleStatus(row: AdminSupplier) {
    try {
      await updateSupplier(row.id, {
        status: row.status === "active" ? "inactive" : "active",
      });
      push(row.status === "active" ? "Supplier deactivated" : "Supplier activated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    }
  }

  const panelOpen = creating || Boolean(editing);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Suppliers" }]} />
      <PageHeader
        title="Suppliers"
        description="Vendor records for inventory purchasing."
        actions={
          canAdjust ? (
            <Button onClick={openCreate}>Add supplier</Button>
          ) : null
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <div className={`grid gap-4 ${panelOpen ? "xl:grid-cols-[1.3fr_0.9fr]" : ""}`}>
          <div>
            {rows.length === 0 ? (
              <EmptyState title="No suppliers" />
            ) : (
              <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-3 py-2.5">Code</th>
                      <th className="px-3 py-2.5">Name</th>
                      <th className="px-3 py-2.5">Contact</th>
                      <th className="px-3 py-2.5">Location</th>
                      <th className="px-3 py-2.5">GSTIN</th>
                      <th className="px-3 py-2.5">Status</th>
                      <th className="px-3 py-2.5">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row) => (
                      <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-3 py-2.5 font-mono text-xs">{row.code}</td>
                        <td className="px-3 py-2.5 font-medium">{row.name}</td>
                        <td className="px-3 py-2.5 text-xs">
                          {row.contact_person ?? "—"}
                          <div className="text-[var(--admin-muted)]">
                            {row.email ?? row.phone ?? ""}
                          </div>
                        </td>
                        <td className="px-3 py-2.5 text-xs">
                          {[row.city, row.state].filter(Boolean).join(", ") || "—"}
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs">{row.gstin ?? "—"}</td>
                        <td className="px-3 py-2.5">
                          <Badge tone={row.status === "active" ? "success" : "neutral"}>
                            {row.status}
                          </Badge>
                        </td>
                        <td className="px-3 py-2.5">
                          <div className="flex flex-wrap gap-2 text-sm">
                            <button
                              type="button"
                              className="text-[var(--admin-primary)] hover:underline"
                              onClick={() => openEdit(row)}
                            >
                              Edit
                            </button>
                            <button
                              type="button"
                              className="text-[var(--admin-info)] hover:underline"
                              onClick={() => void toggleStatus(row)}
                            >
                              {row.status === "active" ? "Deactivate" : "Activate"}
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {panelOpen ? (
            <FormSection
              title={creating ? "New supplier" : `Edit ${editing?.code ?? ""}`}
              description="Fields from SupplierWritePayload."
            >
              <form className="space-y-3" onSubmit={onSubmit}>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input
                    label="Code"
                    value={form.code}
                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                    required
                  />
                  <Input
                    label="Name"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    required
                  />
                  <Input
                    label="Email"
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                  />
                  <Input
                    label="Phone"
                    value={form.phone}
                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                  />
                  <Input
                    label="Contact person"
                    value={form.contact_person}
                    onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                  />
                  <Input
                    label="GSTIN"
                    value={form.gstin}
                    onChange={(e) => setForm({ ...form, gstin: e.target.value })}
                  />
                  <Input
                    label="City"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                  />
                  <Input
                    label="State"
                    value={form.state}
                    onChange={(e) => setForm({ ...form, state: e.target.value })}
                  />
                  <Select
                    label="Status"
                    value={form.status}
                    onChange={(e) => setForm({ ...form, status: e.target.value })}
                  >
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                  </Select>
                </div>
                <div className="flex gap-2">
                  <Button type="submit" disabled={saving}>
                    {saving ? "Saving…" : creating ? "Create" : "Save"}
                  </Button>
                  <Button type="button" variant="secondary" onClick={closePanel}>
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
