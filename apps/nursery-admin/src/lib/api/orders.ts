import { apiGet, apiSend } from "@/lib/api/client";
import type { AdminOrderDetail, AdminOrderListItem } from "@/lib/types";

export async function fetchOrders(params: {
  status?: string;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminOrderListItem[]>("/admin/orders", params);
}

export async function fetchOrder(id: number) {
  return apiGet<AdminOrderDetail>(`/admin/orders/${id}`);
}

export async function updateOrderStatus(
  id: number,
  payload: {
    status: string;
    note?: string;
    tracking_number?: string;
    carrier?: string;
    tracking_url?: string;
  },
) {
  return apiSend<{ id: number; order_number: string; status: string }>(
    "post",
    `/admin/orders/${id}/status`,
    payload,
  );
}
