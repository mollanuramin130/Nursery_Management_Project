import { apiGet } from "@/lib/api/client";

export type AuditLog = {
  id: number;
  actor_user_id?: number | null;
  action: string;
  entity_type?: string | null;
  entity_id?: number | null;
  ip?: string | null;
  request_id?: string | null;
  created_at?: string | null;
};

export async function fetchAuditLogs(params?: {
  entity_type?: string;
  actor_user_id?: number;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AuditLog[]>("/admin/audit-logs", params as Record<string, unknown>);
}
