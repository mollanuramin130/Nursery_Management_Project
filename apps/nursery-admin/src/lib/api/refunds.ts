import { apiGet, apiSend } from "@/lib/api/client";

export type AdminRefund = {
  id: number;
  order_id: number;
  order_number?: string | null;
  payment_id?: number | null;
  customer?: {
    id?: number | null;
    name?: string | null;
    email?: string | null;
  };
  amount: number;
  currency?: string;
  status: string;
  reason?: string | null;
  mode?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export async function fetchRefunds(params?: {
  q?: string;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminRefund[]>("/admin/refunds", params);
}

export async function createRefund(payload: {
  order_id: number;
  payment_id?: number | null;
  amount: number;
  reason?: string;
  note?: string;
}) {
  return apiSend<{
    id: number;
    order_id: number;
    order_number: string;
    amount: number;
    status: string;
    order_status: string;
  }>("post", "/admin/refunds", payload);
}
