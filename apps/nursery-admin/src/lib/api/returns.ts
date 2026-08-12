import { apiGet, apiSend } from "@/lib/api/client";

export type AdminReturnItem = {
  id: number;
  order_item_id: number;
  quantity: number;
  reason?: string | null;
  received_qty?: number | null;
  accepted_qty?: number | null;
  rejected_qty?: number | null;
  condition?: string | null;
  disposition?: string | null;
};

export type AdminReturnRow = {
  id: number;
  order_id: number;
  order_number?: string | null;
  status: string;
  notes?: string | null;
  created_at?: string | null;
  user?: { id?: number; name?: string | null; email?: string | null };
  customer?: { id?: number; name?: string | null; email?: string | null };
  items?: AdminReturnItem[];
  suggested_refund_amount?: number;
};

export type AdminReturnDetail = Omit<AdminReturnRow, "items"> & {
  decided_at?: string | null;
  received_at?: string | null;
  completed_at?: string | null;
  timeline?: Array<{ status: string; at?: string; note?: string | null }>;
  user?: { id?: number; name?: string | null; email?: string | null };
  pickup_tracking?: {
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    scheduled_at?: string | null;
  };
  pickup?: {
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    scheduled_at?: string | null;
  };
  reverse_shipment?: Record<string, unknown> | null;
  inspection?: Record<string, unknown> | null;
  rejection_reason?: string | null;
  refund_id?: number | null;
  actions?: Record<string, boolean>;
  items: AdminReturnItem[];
};

export type ReturnsDashboard = {
  pending_review?: number;
  approved?: number;
  awaiting_pickup?: number;
  awaiting_inspection?: number;
  completed?: number;
  rejected?: number;
  open_total?: number;
  return_requests?: number;
  [key: string]: number | string | undefined;
};

export async function fetchReturnsDashboard() {
  return apiGet<ReturnsDashboard>("/admin/returns/dashboard");
}

export async function fetchReturns(params?: {
  status?: string;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminReturnRow[]>("/admin/returns", params as Record<string, unknown>);
}

export async function fetchReturn(id: number) {
  return apiGet<AdminReturnDetail>(`/admin/returns/${id}`);
}

export async function approveReturn(id: number, note?: string) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/approve`, { note });
}

export async function rejectReturn(id: number, reason: string) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/reject`, { reason });
}

export async function scheduleReturnPickup(
  id: number,
  payload?: { carrier?: string; tracking_number?: string; eta_date?: string },
) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/schedule-pickup`, payload);
}

export async function markReturnPickedUp(id: number) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/picked-up`);
}

export async function receiveReturn(
  id: number,
  items: Array<{ return_item_id: number; received_qty: number; condition: string }>,
  note?: string,
) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/receive`, { items, note });
}

export async function inspectReturn(
  id: number,
  payload: {
    items: Array<{
      return_item_id: number;
      accepted_qty: number;
      rejected_qty?: number;
      disposition: string;
    }>;
    create_refund?: boolean;
    refund_amount?: number;
    note?: string;
    idempotency_key?: string;
  },
) {
  return apiSend<AdminReturnDetail>("post", `/admin/returns/${id}/inspect`, payload);
}

export async function refundReturn(
  id: number,
  payload?: { amount?: number; reason?: string; idempotency_key?: string },
) {
  return apiSend("post", `/admin/returns/${id}/refund`, payload);
}
