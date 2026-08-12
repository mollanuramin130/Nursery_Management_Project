import { apiGet, apiSend } from "@/lib/api/client";

export type AdminSubscription = {
  id: number;
  subscription_number: string;
  status: string;
  product_name?: string | null;
  plan_name?: string | null;
  quantity: number;
  frequency: string;
  unit_price: number;
  currency: string;
  next_billing_at?: string | null;
  cycle_count: number;
  user?: { id: number; name?: string; email?: string };
  actions?: Record<string, boolean>;
  cycles?: Array<{
    id: number;
    cycle_number: number;
    status: string;
    order_id?: number | null;
    order_number?: string | null;
    amount: number;
  }>;
  events?: Array<{ event_type: string; created_at?: string | null; payload?: unknown }>;
  shipping_address?: Record<string, string | null>;
};

export type SubscriptionPlan = {
  id: number;
  product_id: number;
  product_name?: string | null;
  name: string;
  slug: string;
  frequency: string;
  quantity_default: number;
  unit_price: number;
  compare_at_price?: number | null;
  status: string;
  description?: string | null;
};

export type SubscriptionDashboard = {
  active: number;
  paused: number;
  payment_failed: number;
  cancelled: number;
  pending: number;
  due_soon: number;
};

export async function fetchSubscriptionDashboard() {
  return apiGet<SubscriptionDashboard>("/admin/subscriptions/dashboard");
}

export async function fetchSubscriptions(params?: Record<string, unknown>) {
  return apiGet<AdminSubscription[]>("/admin/subscriptions", params);
}

export async function fetchSubscription(id: number) {
  return apiGet<AdminSubscription>(`/admin/subscriptions/${id}`);
}

export async function pauseSubscription(id: number, reason?: string) {
  return apiSend<AdminSubscription>("post", `/admin/subscriptions/${id}/pause`, { reason });
}

export async function resumeSubscription(id: number) {
  return apiSend<AdminSubscription>("post", `/admin/subscriptions/${id}/resume`, {});
}

export async function cancelSubscription(id: number, reason?: string) {
  return apiSend<AdminSubscription>("post", `/admin/subscriptions/${id}/cancel`, { reason });
}

export async function fetchSubscriptionPlans(params?: Record<string, unknown>) {
  return apiGet<SubscriptionPlan[]>("/admin/subscription-plans", params);
}

export async function createSubscriptionPlan(body: Record<string, unknown>) {
  return apiSend<SubscriptionPlan>("post", "/admin/subscription-plans", body);
}

export async function updateSubscriptionPlan(id: number, body: Record<string, unknown>) {
  return apiSend<SubscriptionPlan>("put", `/admin/subscription-plans/${id}`, body);
}
