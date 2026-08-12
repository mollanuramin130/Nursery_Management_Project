"use client";

import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchNotificationTemplates,
  updateNotificationTemplate,
  type NotificationTemplate,
} from "@/lib/api/notifications";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function NotificationTemplatesPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "notifications.view");
  const canManage = hasPermission(user, "notifications.manage_templates");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<NotificationTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editId, setEditId] = useState<number | null>(null);
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission");
      return;
    }
    setLoading(true);
    try {
      const res = await fetchNotificationTemplates();
      setRows(res.data);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  async function save() {
    if (!canManage || !editId) return;
    try {
      await updateNotificationTemplate(editId, { subject, body });
      push("Template saved", "success");
      setEditId(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Notifications", href: "/notifications" },
          { label: "Templates" },
        ]}
      />
      <PageHeader title="Notification templates" description="Variables: {{customer_name}}, {{order_number}}, {{tracking_number}}" />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyState title="No templates" /> : null}

      {editId ? (
        <div className="mb-4 space-y-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
          <Input label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
          <label className="block text-sm">
            Body
            <textarea
              className="mt-1 w-full rounded border border-[var(--admin-border)] p-2 text-sm"
              rows={4}
              value={body}
              onChange={(e) => setBody(e.target.value)}
            />
          </label>
          <div className="flex gap-2">
            <Button onClick={() => void save()}>Save</Button>
            <Button variant="secondary" onClick={() => setEditId(null)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      {!loading && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-[var(--admin-surface-2)] text-left text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Code</th>
                <th className="px-3 py-2">Channel</th>
                <th className="px-3 py-2">Subject</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-[var(--admin-border)]">
                  <td className="px-3 py-2 font-medium">{r.code}</td>
                  <td className="px-3 py-2">{r.channel}</td>
                  <td className="px-3 py-2">{r.subject}</td>
                  <td className="px-3 py-2">{r.status}</td>
                  <td className="px-3 py-2">
                    {canManage ? (
                      <Button
                        variant="secondary"
                        onClick={() => {
                          setEditId(r.id);
                          setSubject(r.subject ?? "");
                          setBody(r.body ?? "");
                        }}
                      >
                        Edit
                      </Button>
                    ) : (
                      "—"
                    )}
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
