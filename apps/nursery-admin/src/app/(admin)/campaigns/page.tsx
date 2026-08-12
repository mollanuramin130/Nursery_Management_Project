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
import {
  deleteCampaign,
  fetchCampaigns,
  updateCampaign,
  type AdminCampaign,
} from "@/lib/api/campaigns";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "draft") return "warning" as const;
  return "neutral" as const;
}

export default function CampaignsPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "campaigns.manage");
  const push = useToastStore((s) => s.push);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [applied, setApplied] = useState({ q: "", status: "" });
  const [rows, setRows] = useState<AdminCampaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminCampaign | null>(null);
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
      const res = await fetchCampaigns({
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

  async function toggleStatus(row: AdminCampaign) {
    const next = row.status === "active" ? "inactive" : "active";
    try {
      await updateCampaign(row.id, { status: next });
      push(next === "active" ? "Campaign activated" : "Campaign deactivated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    }
  }

  async function onDelete() {
    if (!pendingDelete) return;
    setBusy(true);
    try {
      await deleteCampaign(pendingDelete.id);
      push("Campaign deleted", "success");
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
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Campaigns" }]} />
      <PageHeader
        title="Campaigns"
        description="Seasonal and promotional campaign management."
        actions={
          <PermissionGate permission="campaigns.manage">
            <Link href="/campaigns/new">
              <Button>Create campaign</Button>
            </Link>
          </PermissionGate>
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} className="min-w-[200px]" />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="draft">draft</option>
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </Select>
        <Button onClick={() => setApplied({ q, status })}>Apply</Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No campaigns" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5">Title</th>
                <th className="px-3 py-2.5">Type</th>
                <th className="px-3 py-2.5">Schedule</th>
                <th className="px-3 py-2.5">Products</th>
                <th className="px-3 py-2.5">Priority</th>
                <th className="px-3 py-2.5">Status</th>
                <th className="px-3 py-2.5">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5">
                    <div className="font-medium">{row.title}</div>
                    <div className="font-mono text-xs text-[var(--admin-muted)]">{row.slug}</div>
                  </td>
                  <td className="px-3 py-2.5 text-xs">
                    {row.type ?? "—"}
                    {row.season_code ? (
                      <div className="text-[var(--admin-muted)]">{row.season_code}</div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2.5 text-xs">
                    {formatDateTime(row.starts_at)}
                    <br />
                    {formatDateTime(row.ends_at)}
                  </td>
                  <td className="px-3 py-2.5">{row.product_count ?? row.product_ids?.length ?? 0}</td>
                  <td className="px-3 py-2.5">{row.priority ?? 0}</td>
                  <td className="px-3 py-2.5">
                    <Badge tone={statusTone(row.status)}>{row.status}</Badge>
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="flex flex-wrap gap-2 text-sm">
                      <Link
                        href={`/campaigns/${row.id}/edit`}
                        className="text-[var(--admin-primary)] hover:underline"
                      >
                        Edit
                      </Link>
                      <button
                        type="button"
                        className="text-[var(--admin-info)] hover:underline"
                        onClick={() => void toggleStatus(row)}
                      >
                        {row.status === "active" ? "Deactivate" : "Activate"}
                      </button>
                      <button
                        type="button"
                        className="text-[var(--admin-danger)] hover:underline"
                        onClick={() => setPendingDelete(row)}
                      >
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
        title="Delete campaign?"
        description={`Remove “${pendingDelete?.title ?? ""}”. Storefront links may break.`}
        danger
        loading={busy}
        confirmLabel="Delete"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => void onDelete()}
      />
    </div>
  );
}
