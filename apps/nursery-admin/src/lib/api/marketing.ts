import { apiGet, apiSend } from "@/lib/api/client";

export type MarketingDashboard = {
  automations: {
    total: number;
    by_status: Record<string, number>;
    active: Array<{ id: number; key: string; name: string; type: string }>;
    draft: number;
  };
  segments: Array<{ id: number; key: string; name: string }>;
  merchandising_campaigns: Array<{
    id: number;
    title: string;
    slug: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
  }>;
  deliveries_last_20: Array<{
    id: number;
    automation_id: number | null;
    user_id: number;
    status: string;
    skip_reason?: string | null;
    sent_at?: string | null;
    created_at?: string | null;
  }>;
  attribution: {
    supported: boolean;
    orders_with_campaign_id: number;
    revenue_with_campaign_id: number;
    note: string;
  };
  config: {
    abandoned_cart_hours: number;
    max_marketing_per_day: number;
  };
};

export type MarketingAutomation = {
  id: number;
  key: string;
  name: string;
  type: string;
  status: string;
  segment_id?: number | null;
  segment?: { id: number; key: string; name: string } | null;
  campaign_id?: number | null;
  campaign?: { id: number; title: string; slug: string } | null;
  coupon_id?: number | null;
  coupon?: { id: number; code: string; name: string } | null;
  channels: string[];
  config: Record<string, unknown>;
  title_template?: string | null;
  body_template?: string | null;
  scheduled_at?: string | null;
  last_run_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  delivery_stats?: { sent: number; failed: number; skipped: number };
};

export type AutomationPayload = {
  name: string;
  key?: string;
  type: string;
  status?: string;
  segment_id?: number | null;
  campaign_id?: number | null;
  coupon_id?: number | null;
  channels?: string[];
  config?: Record<string, unknown>;
  title_template?: string;
  body_template?: string;
  scheduled_at?: string | null;
};

export async function fetchMarketingDashboard() {
  return apiGet<MarketingDashboard>("/admin/marketing/dashboard");
}

export async function fetchAutomations(params?: { type?: string }) {
  return apiGet<MarketingAutomation[]>("/admin/marketing/automations", params);
}

export async function fetchAutomation(id: number) {
  return apiGet<MarketingAutomation>(`/admin/marketing/automations/${id}`);
}

export async function createAutomation(payload: AutomationPayload) {
  return apiSend<MarketingAutomation>("post", "/admin/marketing/automations", payload);
}

export async function updateAutomation(id: number, payload: Partial<AutomationPayload>) {
  return apiSend<MarketingAutomation>("put", `/admin/marketing/automations/${id}`, payload);
}

export async function activateAutomation(id: number) {
  return apiSend<MarketingAutomation>("post", `/admin/marketing/automations/${id}/activate`);
}

export async function pauseAutomation(id: number) {
  return apiSend<MarketingAutomation>("post", `/admin/marketing/automations/${id}/pause`);
}

export async function dispatchAutomation(id: number, testUserIds?: number[]) {
  return apiSend<{ sent: number; skipped: number; failed: number }>(
    "post",
    `/admin/marketing/automations/${id}/dispatch`,
    testUserIds?.length ? { test_user_ids: testUserIds } : {},
  );
}
