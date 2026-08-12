import { apiGet, apiSend } from "@/lib/api/client";

export type AdminBanner = {
  id: number;
  title: string;
  image_url: string;
  placement: string;
  link_type?: string | null;
  link_value?: string | null;
  sort_order: number;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
};

export type BannerWritePayload = {
  title: string;
  image_url: string;
  placement: string;
  link_type?: string | null;
  link_value?: string | null;
  sort_order?: number;
  starts_at?: string | null;
  ends_at?: string | null;
  status?: string;
};

export async function fetchBanners() {
  return apiGet<AdminBanner[]>("/admin/banners");
}

export async function fetchBanner(id: number) {
  return apiGet<AdminBanner>(`/admin/banners/${id}`);
}

export async function createBanner(payload: BannerWritePayload) {
  return apiSend<{ id: number; title: string }>("post", "/admin/banners", payload);
}

export async function updateBanner(id: number, payload: Partial<BannerWritePayload>) {
  return apiSend<{ id: number; status: string }>("put", `/admin/banners/${id}`, payload);
}

export async function deleteBanner(id: number) {
  return apiSend("delete", `/admin/banners/${id}`);
}
