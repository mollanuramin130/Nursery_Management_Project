import { apiGet, apiSend } from "@/lib/api/client";
import type { AdminCategory } from "@/lib/types";

export async function fetchCategories() {
  return apiGet<AdminCategory[]>("/admin/categories");
}

export type CategoryWritePayload = {
  name: string;
  slug?: string;
  parent_id?: number | null;
  image_url?: string | null;
  sort_order?: number;
  status?: string;
  description?: string | null;
};

export async function createCategory(payload: CategoryWritePayload) {
  return apiSend<{ id: number; slug: string; name: string }>(
    "post",
    "/admin/categories",
    payload,
  );
}

export async function updateCategory(id: number, payload: Partial<CategoryWritePayload>) {
  return apiSend<{ id: number; slug: string; name: string; status: string }>(
    "put",
    `/admin/categories/${id}`,
    payload,
  );
}

export async function deleteCategory(id: number) {
  return apiSend("delete", `/admin/categories/${id}`);
}
