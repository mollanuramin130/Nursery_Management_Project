"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { ApiError } from "@/lib/api/client";
import { fetchAdminNotification, type AdminNotification } from "@/lib/api/notifications";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

export default function AdminNotificationDetailPage() {
  const id = Number(useParams().id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "notifications.view");
  const [row, setRow] = useState<AdminNotification | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView || !id) {
      setLoading(false);
      setError("Missing permission or id");
      return;
    }
    setLoading(true);
    try {
      const res = await fetchAdminNotification(id);
      setRow(res.data);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Notifications", href: "/notifications" },
          { label: `#${id}` },
        ]}
      />
      <PageHeader title={row?.title ?? `Notification #${id}`} description={row?.type} />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {row && !loading ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4 text-sm">
            <p>
              <Badge>{row.category}</Badge> {row.is_read ? "Read" : "Unread"}
            </p>
            <p className="mt-2">{row.body}</p>
            <p className="mt-2 text-[var(--admin-muted)]">
              {row.user?.email} · {row.created_at ? formatDateTime(row.created_at) : ""}
            </p>
          </div>
          <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
            <h3 className="mb-2 font-semibold">Deliveries</h3>
            <ul className="space-y-2 text-sm">
              {(row.deliveries ?? []).map((d, i) => (
                <li key={i} className="flex justify-between gap-2 border-b border-[var(--admin-border)] py-1">
                  <span>
                    {d.channel} · {d.status} · {d.provider ?? "—"}
                  </span>
                  <span className="text-[var(--admin-muted)]">{d.error_message ?? `${d.attempts} attempts`}</span>
                </li>
              ))}
              {(row.deliveries ?? []).length === 0 ? (
                <li className="text-[var(--admin-muted)]">No delivery rows yet (queue may be pending)</li>
              ) : null}
            </ul>
          </div>
        </div>
      ) : null}
    </div>
  );
}
