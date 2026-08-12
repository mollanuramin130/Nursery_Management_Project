import { apiGet, apiSend } from "@/lib/api/client";

export type AdminNotification = {
  id: number;
  type: string;
  category?: string;
  title: string;
  body?: string;
  is_read: boolean;
  created_at?: string | null;
  user?: { id: number; email?: string; name?: string };
  deliveries?: Array<{
    channel: string;
    status: string;
    provider?: string | null;
    attempts: number;
    error_message?: string | null;
  }>;
};

export type NotificationDashboard = {
  total: number;
  unread: number;
  marketing: number;
  transactional: number;
  deliveries_failed: number;
  deliveries_sent: number;
};

export type NotificationTemplate = {
  id: number;
  code: string;
  name?: string | null;
  channel: string;
  subject?: string | null;
  body?: string | null;
  status: string;
};

export async function fetchNotificationDashboard() {
  return apiGet<NotificationDashboard>("/admin/notifications/dashboard");
}

export async function fetchAdminNotifications(params?: Record<string, unknown>) {
  return apiGet<AdminNotification[]>("/admin/notifications", params);
}

export async function fetchAdminNotification(id: number) {
  return apiGet<AdminNotification>(`/admin/notifications/${id}`);
}

export async function fetchNotificationTemplates() {
  return apiGet<NotificationTemplate[]>("/admin/notification-templates");
}

export async function updateNotificationTemplate(id: number, body: Record<string, unknown>) {
  return apiSend<NotificationTemplate[]>("put", `/admin/notification-templates/${id}`, body);
}

export async function sendAdminNotification(body: Record<string, unknown>) {
  return apiSend<AdminNotification>("post", "/admin/notifications/send", body);
}
