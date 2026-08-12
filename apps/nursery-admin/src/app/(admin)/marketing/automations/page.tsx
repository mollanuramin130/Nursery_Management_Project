"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  fetchAutomations,
  type MarketingAutomation,
} from "@/lib/api/marketing";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "draft") return "warning" as const;
  if (status === "paused") return "neutral" as const;
  return "neutral" as const;
}

export default function AutomationsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "marketing.view");
  const [type, setType] = useState("");
  const [rows, setRows] = useState<MarketingAutomation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: marketing.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchAutomations(type ? { type } : undefined);
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, type]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Marketing", href: "/marketing" },
          { label: "Automations" },
        ]}
      />
      <PageHeader
        title="Marketing automations"
        description="Welcome, abandoned cart, post-purchase, reactivation, and manual blasts."
        actions={
          <PermissionGate permission="marketing.manage">
            <Link href="/marketing/automations/new">
              <Button>Create automation</Button>
            </Link>
          </PermissionGate>
        }
      />

      <div className="mb-4 max-w-xs">
        <Select
          value={type}
          onChange={(e) => setType(e.target.value)}
          aria-label="Filter by type"
        >
          <option value="">All types</option>
          <option value="abandoned_cart">Abandoned cart</option>
          <option value="welcome">Welcome</option>
          <option value="post_purchase">Post-purchase</option>
          <option value="reactivation">Reactivation</option>
          <option value="manual_blast">Manual blast</option>
        </Select>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && rows.length === 0 ? (
        <EmptyState title="No automations" description="Create one or run marketing:seed on the API." />
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-lg border border-[var(--admin-border)]">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-[var(--admin-surface)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Name</th>
                <th className="px-3 py-2">Type</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Audience</th>
                <th className="px-3 py-2">Last run</th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-t border-[var(--admin-border)]/60">
                  <td className="px-3 py-2 font-medium">{row.name}</td>
                  <td className="px-3 py-2">{row.type}</td>
                  <td className="px-3 py-2">
                    <Badge tone={statusTone(row.status)}>{row.status}</Badge>
                  </td>
                  <td className="px-3 py-2 text-xs">{row.segment?.name ?? "—"}</td>
                  <td className="px-3 py-2 text-xs">{formatDateTime(row.last_run_at)}</td>
                  <td className="px-3 py-2 text-right">
                    <Link href={`/marketing/automations/${row.id}`} className="text-[var(--admin-primary)] hover:underline">
                      Open
                    </Link>
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
