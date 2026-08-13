"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchAdminNotifications,
  fetchNotificationDashboard,
  sendAdminNotification,
  type AdminNotification,
  type NotificationDashboard,
} from "@/lib/api/notifications";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function AdminNotificationsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "notifications.view");
  const canSend = hasPermission(user, "notifications.send");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<AdminNotification[]>([]);
  const [dash, setDash] = useState<NotificationDashboard | null>(null);
  const [category, setCategory] = useState("");
  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sendUserId, setSendUserId] = useState("");
  const [sendTitle, setSendTitle] = useState("");
  const [sendBody, setSendBody] = useState("");

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: notifications.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [list, d] = await Promise.all([
        fetchAdminNotifications({
          category: category || undefined,
          q: debouncedQ || undefined,
          per_page: 30,
        }),
        fetchNotificationDashboard(),
      ]);
      setRows(list.data);
      setDash(d.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, category, debouncedQ]);

  useEffect(() => {
    void load();
  }, [load]);

  async function send() {
    if (!canSend) return;
    try {
      await sendAdminNotification({
        user_id: Number(sendUserId),
        title: sendTitle,
        body: sendBody,
        type: "campaign_promo",
      });
      push("Queued (respects marketing opt-in)", "success");
      setSendTitle("");
      setSendBody("");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Notifications" }]} />
      <PageHeader
        title="Notifications"
        description="Monitor in-app / email / push delivery. Provider failures never roll back orders."
        actions={
          <Link href="/notification-templates">
            <Button variant="secondary">Templates</Button>
          </Link>
        }
      />

      {dash ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
          {(
            [
              ["Total", dash.total],
              ["Unread", dash.unread],
              ["Transactional", dash.transactional],
              ["Marketing", dash.marketing],
              ["Sent", dash.deliveries_sent],
              ["Failed", dash.deliveries_failed],
            ] as const
          ).map(([label, value]) => (
            <div
              key={label}
              className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3"
            >
              <div className="text-xs uppercase text-[var(--admin-muted)]">{label}</div>
              <div className="text-2xl font-semibold tabular-nums">{value ?? 0}</div>
            </div>
          ))}
        </div>
      ) : null}

      {canSend ? (
        <div className="mb-4 grid gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3 sm:grid-cols-4">
          <Input label="Customer user ID" value={sendUserId} onChange={(e) => setSendUserId(e.target.value)} />
          <Input label="Title" value={sendTitle} onChange={(e) => setSendTitle(e.target.value)} />
          <Input label="Body" value={sendBody} onChange={(e) => setSendBody(e.target.value)} />
          <div className="flex items-end">
            <Button onClick={() => void send()}>Send marketing</Button>
          </div>
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} />
        <Select label="Category" value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="">All</option>
          <option value="transactional">Transactional</option>
          <option value="marketing">Marketing</option>
        </Select>
        <Button onClick={() => void load()}>Refresh</Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No notifications" /> : null}

      {!loading && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-[var(--admin-surface-2)] text-left text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">ID</th>
                <th className="px-3 py-2">Customer</th>
                <th className="px-3 py-2">Type</th>
                <th className="px-3 py-2">Title</th>
                <th className="px-3 py-2">Category</th>
                <th className="px-3 py-2">Read</th>
                <th className="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-[var(--admin-border)]">
                  <td className="px-3 py-2">
                    <Link className="text-[var(--admin-accent)]" href={`/notifications/${r.id}`}>
                      #{r.id}
                    </Link>
                  </td>
                  <td className="px-3 py-2">{r.user?.email ?? r.user?.id}</td>
                  <td className="px-3 py-2">{r.type}</td>
                  <td className="px-3 py-2">{r.title}</td>
                  <td className="px-3 py-2">
                    <Badge>{r.category ?? "—"}</Badge>
                  </td>
                  <td className="px-3 py-2">{r.is_read ? "Yes" : "No"}</td>
                  <td className="px-3 py-2">{r.created_at ? formatDateTime(r.created_at) : "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
