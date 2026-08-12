"use client";

import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchAuditLogs, type AuditLog } from "@/lib/api/audit";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import type { Pagination } from "@/lib/types";
import { useAuthStore } from "@/store/auth";

export default function AuditLogsPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "users.manage");
  const [entityType, setEntityType] = useState("");
  const [actorUserId, setActorUserId] = useState("");
  const [applied, setApplied] = useState({ entity_type: "", actor_user_id: "" });
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<AuditLog[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError("Missing permission: users.manage");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const actor =
        applied.actor_user_id.trim() !== "" ? Number(applied.actor_user_id) : undefined;
      const res = await fetchAuditLogs({
        entity_type: applied.entity_type || undefined,
        actor_user_id: Number.isFinite(actor) ? actor : undefined,
        page,
        per_page: 20,
      });
      setRows(res.data);
      setPagination(res.meta?.pagination ?? null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage, applied, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Audit logs" }]} />
      <PageHeader
        title="Audit logs"
        description="Admin mutation trail from audit_logs."
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input
          label="Entity type"
          value={entityType}
          onChange={(e) => setEntityType(e.target.value)}
          placeholder="e.g. order, product"
          className="min-w-[180px]"
        />
        <Input
          label="Actor user ID"
          type="number"
          value={actorUserId}
          onChange={(e) => setActorUserId(e.target.value)}
          className="min-w-[140px]"
        />
        <Button
          onClick={() => {
            setPage(1);
            setApplied({ entity_type: entityType, actor_user_id: actorUserId });
          }}
        >
          Apply
        </Button>
        <Button
          variant="secondary"
          onClick={() => {
            setEntityType("");
            setActorUserId("");
            setPage(1);
            setApplied({ entity_type: "", actor_user_id: "" });
          }}
        >
          Clear
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No audit logs" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5">ID</th>
                <th className="px-3 py-2.5">When</th>
                <th className="px-3 py-2.5">Actor</th>
                <th className="px-3 py-2.5">Action</th>
                <th className="px-3 py-2.5">Entity</th>
                <th className="px-3 py-2.5">IP</th>
                <th className="px-3 py-2.5">Request</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5 font-mono text-xs">#{row.id}</td>
                  <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                  <td className="px-3 py-2.5 font-mono text-xs">
                    {row.actor_user_id != null ? `#${row.actor_user_id}` : "—"}
                  </td>
                  <td className="px-3 py-2.5 font-medium">{row.action}</td>
                  <td className="px-3 py-2.5 text-xs">
                    {row.entity_type ?? "—"}
                    {row.entity_id != null ? ` #${row.entity_id}` : ""}
                  </td>
                  <td className="px-3 py-2.5 font-mono text-xs">{row.ip ?? "—"}</td>
                  <td className="px-3 py-2.5 font-mono text-xs">{row.request_id ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {pagination ? (
            <div className="flex items-center justify-between gap-3 border-t border-[var(--admin-border)] px-3 py-2 text-xs text-[var(--admin-muted)]">
              <span>
                Page {pagination.current_page} of {pagination.last_page} · {pagination.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
