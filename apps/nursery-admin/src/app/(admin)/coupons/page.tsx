"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Input, Select } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import { deleteCoupon, fetchCoupons, updateCoupon, type AdminCoupon } from "@/lib/api/coupons";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function CouponsPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "campaigns.manage");
  const push = useToastStore((s) => s.push);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [applied, setApplied] = useState({ q: "", status: "" });
  const [rows, setRows] = useState<AdminCoupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminCoupon | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError("Missing permission: campaigns.manage");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCoupons({
        q: applied.q || undefined,
        status: applied.status || undefined,
      });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage, applied]);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggleStatus(row: AdminCoupon) {
    try {
      await updateCoupon(row.id, {
        status: row.status === "active" ? "inactive" : "active",
      });
      push(row.status === "active" ? "Coupon deactivated" : "Coupon activated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    }
  }

  async function onDelete() {
    if (!pendingDelete) return;
    setBusy(true);
    try {
      await deleteCoupon(pendingDelete.id);
      push("Coupon deleted", "success");
      setPendingDelete(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Delete failed", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Coupons" }]} />
      <PageHeader
        title="Coupons"
        description="Discount codes for Website and Mobile checkout."
        actions={
          <PermissionGate permission="campaigns.manage">
            <Link href="/coupons/new">
              <Button>Create coupon</Button>
            </Link>
          </PermissionGate>
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} className="min-w-[200px]" />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </Select>
        <Button onClick={() => setApplied({ q, status })}>Apply</Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No coupons" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5">Code</th>
                <th className="px-3 py-2.5">Discount</th>
                <th className="px-3 py-2.5">Min order</th>
                <th className="px-3 py-2.5">Usage</th>
                <th className="px-3 py-2.5">Validity</th>
                <th className="px-3 py-2.5">Status</th>
                <th className="px-3 py-2.5">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5">
                    <div className="font-mono font-medium">{row.code}</div>
                    <div className="text-xs text-[var(--admin-muted)]">{row.name}</div>
                  </td>
                  <td className="px-3 py-2.5">
                    {row.discount_type === "percent"
                      ? `${row.discount_value}%`
                      : formatMoney(row.discount_value)}
                    {row.max_discount_amount != null ? (
                      <div className="text-xs text-[var(--admin-muted)]">
                        max {formatMoney(row.max_discount_amount)}
                      </div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2.5">
                    {row.min_order_amount != null ? formatMoney(row.min_order_amount) : "—"}
                  </td>
                  <td className="px-3 py-2.5 text-xs">
                    {row.used_count ?? 0}
                    {row.usage_limit_total != null ? ` / ${row.usage_limit_total}` : ""}
                  </td>
                  <td className="px-3 py-2.5 text-xs">
                    {formatDateTime(row.starts_at)}
                    <br />
                    {formatDateTime(row.ends_at)}
                  </td>
                  <td className="px-3 py-2.5">
                    <Badge tone={row.status === "active" ? "success" : "neutral"}>{row.status}</Badge>
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="flex flex-wrap gap-2 text-sm">
                      <Link href={`/coupons/${row.id}/edit`} className="text-[var(--admin-primary)] hover:underline">
                        Edit
                      </Link>
                      <button type="button" className="text-[var(--admin-info)] hover:underline" onClick={() => void toggleStatus(row)}>
                        {row.status === "active" ? "Deactivate" : "Activate"}
                      </button>
                      <button type="button" className="text-[var(--admin-danger)] hover:underline" onClick={() => setPendingDelete(row)}>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      <ConfirmDialog
        open={Boolean(pendingDelete)}
        title="Delete coupon?"
        description={`Customers will no longer be able to use ${pendingDelete?.code ?? ""}.`}
        danger
        loading={busy}
        confirmLabel="Delete"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => void onDelete()}
      />
    </div>
  );
}
