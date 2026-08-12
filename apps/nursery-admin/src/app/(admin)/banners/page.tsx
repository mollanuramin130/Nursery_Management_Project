"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  deleteBanner,
  fetchBanners,
  updateBanner,
  type AdminBanner,
} from "@/lib/api/banners";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function BannersPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "campaigns.manage");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<AdminBanner[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminBanner | null>(null);
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
      const res = await fetchBanners();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage]);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggleStatus(row: AdminBanner) {
    try {
      await updateBanner(row.id, {
        status: row.status === "active" ? "inactive" : "active",
      });
      push(row.status === "active" ? "Banner deactivated" : "Banner activated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    }
  }

  async function onDelete() {
    if (!pendingDelete) return;
    setBusy(true);
    try {
      await deleteBanner(pendingDelete.id);
      push("Banner deleted", "success");
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
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Banners" }]} />
      <PageHeader
        title="Banners"
        description="Home and placement banners for Website and Mobile."
        actions={
          <PermissionGate permission="campaigns.manage">
            <Link href="/banners/new">
              <Button>Create banner</Button>
            </Link>
          </PermissionGate>
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No banners" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5">Title</th>
                <th className="px-3 py-2.5">Placement</th>
                <th className="px-3 py-2.5">Link</th>
                <th className="px-3 py-2.5">Sort</th>
                <th className="px-3 py-2.5">Schedule</th>
                <th className="px-3 py-2.5">Status</th>
                <th className="px-3 py-2.5">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5">
                    <div className="flex items-center gap-2">
                      <div className="h-10 w-14 overflow-hidden rounded bg-[var(--admin-surface-muted)]">
                        {row.image_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={row.image_url} alt="" className="h-full w-full object-cover" />
                        ) : null}
                      </div>
                      <span className="font-medium">{row.title}</span>
                    </div>
                  </td>
                  <td className="px-3 py-2.5 font-mono text-xs">{row.placement}</td>
                  <td className="px-3 py-2.5 text-xs">
                    {row.link_type ?? "—"}
                    {row.link_value ? (
                      <div className="max-w-[180px] truncate text-[var(--admin-muted)]">
                        {row.link_value}
                      </div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2.5">{row.sort_order}</td>
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
                      <Link
                        href={`/banners/${row.id}/edit`}
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
        title="Delete banner?"
        description={`Remove “${pendingDelete?.title ?? ""}”.`}
        danger
        loading={busy}
        confirmLabel="Delete"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => void onDelete()}
      />
    </div>
  );
}
