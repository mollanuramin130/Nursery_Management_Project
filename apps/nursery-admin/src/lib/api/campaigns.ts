import { apiGet, apiSend } from "@/lib/api/client";

export type AdminCampaign = {
  id: number;
  slug: string;
  title: string;
  subtitle?: string | null;
  description?: string | null;
  type?: string | null;
  season_code?: string | null;
  status: string;
  image_url?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  priority?: number;
  product_count?: number;
  product_ids?: number[];
  created_at?: string | null;
};

export type CampaignWritePayload = {
  title: string;
  slug?: string;
  subtitle?: string | null;
  description?: string | null;
  type?: string;
  season_code?: string | null;
  image_url?: string | null;
  starts_at: string;
  ends_at: string;
  status?: string;
  priority?: number;
  product_ids?: number[];
};

export async function fetchCampaigns(params?: { q?: string; status?: string }) {
  return apiGet<AdminCampaign[]>("/admin/campaigns", params);
}

export async function fetchCampaign(id: number) {
  return apiGet<AdminCampaign>(`/admin/campaigns/${id}`);
}

export async function createCampaign(payload: CampaignWritePayload) {
  return apiSend<{ id: number; slug: string; status: string }>("post", "/admin/campaigns", payload);
}

export async function updateCampaign(id: number, payload: Partial<CampaignWritePayload>) {
  return apiSend<{ id: number; slug: string; status: string }>(
    "put",
    `/admin/campaigns/${id}`,
    payload,
  );
}

export async function deleteCampaign(id: number) {
  return apiSend("delete", `/admin/campaigns/${id}`);
}
