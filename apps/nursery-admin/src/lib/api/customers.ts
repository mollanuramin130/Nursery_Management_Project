import { apiGet, apiSend } from "@/lib/api/client";

export type AdminCustomer = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  status: string;
  roles: string[];
  last_login_at?: string | null;
  created_at?: string | null;
  total_orders?: number;
  total_spent?: number;
  recent_orders?: Array<{
    id: number;
    order_number: string;
    status: string;
    grand_total: number;
    placed_at?: string | null;
  }>;
};

export async function fetchCustomers(params?: {
  q?: string;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminCustomer[]>("/admin/users", {
    ...params,
    role: "customer",
  });
}

export async function fetchCustomer(id: number) {
  return apiGet<AdminCustomer>(`/admin/users/${id}`);
}

export type Customer360 = {
  profile: {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    status: string;
    roles: string[];
    registered_at?: string | null;
    last_login_at?: string | null;
    lifecycle_stage: string;
  };
  orders: {
    total_orders: number;
    qualifying_orders: number;
    total_spent: number;
    average_order_value: number;
    last_order: {
      id: number;
      order_number: string;
      status: string;
      grand_total: number;
      coupon_code?: string | null;
      campaign_id?: number | null;
      placed_at?: string | null;
    } | null;
    recent: Array<{
      id: number;
      order_number: string;
      status: string;
      grand_total: number;
      placed_at?: string | null;
    }>;
  };
  marketing: {
    preferences: Record<string, unknown>;
    coupon_redemptions: Array<{
      id: number;
      coupon_code?: string | null;
      order_id?: number | null;
      discount_amount?: number | null;
      created_at?: string | null;
    }>;
  };
  loyalty: Record<string, unknown> | null;
  subscriptions: Array<Record<string, unknown>>;
  activity: {
    wishlist_count: number;
    review_count: number;
    recently_viewed_product_ids: number[];
    open_cart: Record<string, unknown> | null;
  };
  definitions: Record<string, string>;
};

export async function fetchCustomer360(id: number) {
  return apiGet<Customer360>(`/admin/customers/${id}/360`);
}

export async function updateCustomerStatus(id: number, status: string) {
  return apiSend<{ id: number; email: string; status: string; roles: string[] }>(
    "put",
    `/admin/users/${id}`,
    { status },
  );
}
