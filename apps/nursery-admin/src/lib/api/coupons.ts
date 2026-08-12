import { apiGet, apiSend } from "@/lib/api/client";

export type AdminCoupon = {
  id: number;
  code: string;
  name: string;
  discount_type: "percent" | "fixed" | string;
  discount_value: number;
  min_order_amount?: number | null;
  max_discount_amount?: number | null;
  usage_limit_total?: number | null;
  usage_limit_per_user?: number | null;
  used_count?: number;
  status: string;
  is_public?: boolean;
  stackable?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
};

export type CouponWritePayload = {
  code: string;
  name: string;
  discount_type: string;
  discount_value: number;
  min_order_amount?: number | null;
  max_discount_amount?: number | null;
  usage_limit_total?: number | null;
  usage_limit_per_user?: number | null;
  starts_at?: string | null;
  ends_at?: string | null;
  status?: string;
  is_public?: boolean;
  stackable?: boolean;
};

export async function fetchCoupons(params?: { q?: string; status?: string }) {
  return apiGet<AdminCoupon[]>("/admin/coupons", params);
}

export async function fetchCoupon(id: number) {
  return apiGet<AdminCoupon>(`/admin/coupons/${id}`);
}

export async function createCoupon(payload: CouponWritePayload) {
  return apiSend<{ id: number; code: string; status: string }>("post", "/admin/coupons", payload);
}

export async function updateCoupon(id: number, payload: Partial<CouponWritePayload>) {
  return apiSend<{ id: number; code: string; status: string }>("put", `/admin/coupons/${id}`, payload);
}

export async function deleteCoupon(id: number) {
  return apiSend("delete", `/admin/coupons/${id}`);
}
