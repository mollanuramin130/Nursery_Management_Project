import { apiGet, apiSend } from "@/lib/api/client";

export type LoyaltyAccountRow = {
  id: number;
  user_id: number;
  customer?: { id?: number; name?: string | null; email?: string | null };
  balance: number;
  lifetime_earned: number;
  lifetime_redeemed: number;
  status: string;
  updated_at?: string | null;
};

export type LoyaltyTx = {
  id: number;
  type: string;
  points: number;
  balance_after: number;
  reason?: string | null;
  user_id?: number;
  customer?: { id?: number; name?: string | null; email?: string | null };
  created_at?: string | null;
};

export type LoyaltyDashboard = {
  accounts?: number;
  points_outstanding?: number;
  points_earned?: number;
  points_reversed?: number;
  points_adjusted?: number;
  enabled?: boolean;
  points_per_rupee?: number;
};

export async function fetchLoyaltyDashboard() {
  return apiGet<LoyaltyDashboard>("/admin/loyalty/dashboard");
}

export async function fetchLoyaltyAccounts(params?: {
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<LoyaltyAccountRow[]>("/admin/loyalty/accounts", params as Record<string, unknown>);
}

export async function fetchLoyaltyTransactions(params?: {
  user_id?: number;
  type?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<LoyaltyTx[]>("/admin/loyalty/transactions", params as Record<string, unknown>);
}

export async function adjustLoyalty(body: {
  user_id: number;
  points: number;
  reason: string;
  idempotency_key?: string;
}) {
  return apiSend("post", "/admin/loyalty/adjust", body);
}
