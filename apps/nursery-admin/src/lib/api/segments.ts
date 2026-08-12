import { apiGet, apiSend } from "@/lib/api/client";

export type SegmentCriterion = {
  field: string;
  op: string;
  value: number | boolean | string;
};

export type CustomerSegment = {
  id: number;
  key: string;
  name: string;
  description?: string | null;
  criteria_json: { all: SegmentCriterion[] };
  is_system: boolean;
  status: string;
  member_count?: number;
  created_at?: string | null;
  updated_at?: string | null;
};

export type SegmentMember = {
  id: number;
  name: string;
  email: string;
  status: string;
  registered_at?: string | null;
};

export type SegmentPayload = {
  name: string;
  key?: string;
  description?: string;
  criteria_json: { all: SegmentCriterion[] };
  status?: string;
};

export async function fetchSegments() {
  return apiGet<CustomerSegment[]>("/admin/customer-segments");
}

export async function fetchSegment(id: number) {
  return apiGet<CustomerSegment>(`/admin/customer-segments/${id}`);
}

export async function createSegment(payload: SegmentPayload) {
  return apiSend<CustomerSegment>("post", "/admin/customer-segments", payload);
}

export async function updateSegment(id: number, payload: Partial<SegmentPayload>) {
  return apiSend<CustomerSegment>("put", `/admin/customer-segments/${id}`, payload);
}

export async function archiveSegment(id: number) {
  return apiSend<null>("post", `/admin/customer-segments/${id}/archive`);
}

export async function duplicateSegment(id: number) {
  return apiSend<CustomerSegment>("post", `/admin/customer-segments/${id}/duplicate`);
}

export async function fetchSegmentMembers(id: number, params?: { page?: number; per_page?: number }) {
  return apiGet<SegmentMember[]>(`/admin/customer-segments/${id}/members`, params);
}
