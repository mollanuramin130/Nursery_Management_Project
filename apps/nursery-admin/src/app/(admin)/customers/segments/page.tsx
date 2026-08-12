"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  archiveSegment,
  duplicateSegment,
  fetchSegments,
  type CustomerSegment,
} from "@/lib/api/segments";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function criteriaSummary(seg: CustomerSegment) {
  const rules = seg.criteria_json?.all ?? [];
  if (!rules.length) return "—";
  return rules.map((r) => `${r.field} ${r.op} ${String(r.value)}`).join(" AND ");
}

export default function SegmentsPage() {
  const user = useAuthStore((s) => s.user);
  const can = hasPermission(user, "customers.segment");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<CustomerSegment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    if (!can) {
      setLoading(false);
      setError("Missing permission: customers.segment");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchSegments();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [can]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onDuplicate(id: number) {
    setBusyId(id);
    try {
      const res = await duplicateSegment(id);
      push("Segment duplicated", "success");
      routerPush(res.data.id);
    } catch (err) {
      push(err instanceof Error ? err.message : "Duplicate failed", "error");
    } finally {
      setBusyId(null);
    }
  }

  function routerPush(id: number) {
    window.location.href = `/customers/segments/${id}`;
  }

  async function onArchive(id: number) {
    setBusyId(id);
    try {
      await archiveSegment(id);
      push("Segment archived", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Archive failed", "error");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Customers", href: "/customers" },
          { label: "Segments" },
        ]}
      />
      <PageHeader
        title="Customer segments"
        description="Server-side dynamic segments. Counts load on detail — never the full customer list in browser."
        actions={
          <PermissionGate permission="customers.segment">
            <Link href="/customers/segments/new">
              <Button>Create segment</Button>
            </Link>
          </PermissionGate>
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && rows.length === 0 ? (
        <EmptyState title="No segments" description="Create a segment or run php artisan marketing:seed." />
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-lg border border-[var(--admin-border)]">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-[var(--admin-surface)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Segment</th>
                <th className="px-3 py-2">Criteria</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Updated</th>
                <th className="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-t border-[var(--admin-border)]/60">
                  <td className="px-3 py-2">
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-[var(--admin-muted)]">{row.key}</div>
                  </td>
                  <td className="max-w-md px-3 py-2 text-xs">{criteriaSummary(row)}</td>
                  <td className="px-3 py-2">
                    <Badge tone={row.status === "active" ? "success" : "neutral"}>{row.status}</Badge>
                    {row.is_system ? <span className="ml-1 text-xs text-[var(--admin-muted)]">system</span> : null}
                  </td>
                  <td className="px-3 py-2 text-xs">{formatDateTime(row.updated_at)}</td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-2">
                      <Link href={`/customers/segments/${row.id}`} className="text-[var(--admin-primary)] hover:underline">
                        View
                      </Link>
                      <button
                        type="button"
                        className="text-[var(--admin-primary)] hover:underline disabled:opacity-50"
                        disabled={busyId === row.id}
                        onClick={() => void onDuplicate(row.id)}
                      >
                        Duplicate
                      </button>
                      {row.status !== "archived" ? (
                        <button
                          type="button"
                          className="text-red-600 hover:underline disabled:opacity-50"
                          disabled={busyId === row.id}
                          onClick={() => void onArchive(row.id)}
                        >
                          Archive
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
