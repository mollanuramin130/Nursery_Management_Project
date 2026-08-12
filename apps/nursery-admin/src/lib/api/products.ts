import { apiGet, apiSend } from "@/lib/api/client";
import type { AdminProductDetail, AdminProductListItem } from "@/lib/types";

export async function fetchProducts(params: {
  status?: string;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminProductListItem[]>("/admin/products", params);
}

export async function fetchProduct(id: number) {
  return apiGet<AdminProductDetail>(`/admin/products/${id}`);
}

export type ProductWritePayload = {
  name: string;
  sku: string;
  slug?: string;
  product_type: string;
  price: number;
  compare_at_price?: number | null;
  status?: string;
  brand_id?: number | null;
  category_ids?: number[];
  tags?: string[];
  description?: string | null;
  is_featured?: boolean;
  is_new?: boolean;
  inventory?: {
    warehouse_id: number;
    qty_on_hand?: number;
    low_stock_threshold?: number;
  };
  plant?: Record<string, unknown> | null;
};

export async function createProduct(payload: ProductWritePayload) {
  return apiSend<{ id: number; sku: string; slug: string; status: string }>(
    "post",
    "/admin/products",
    payload,
  );
}

export async function updateProduct(id: number, payload: Partial<ProductWritePayload>) {
  return apiSend<AdminProductDetail>("put", `/admin/products/${id}`, payload);
}

export async function deleteProduct(id: number) {
  return apiSend("delete", `/admin/products/${id}`);
}

export async function addProductImages(
  id: number,
  images: Array<{
    url: string;
    alt?: string;
    is_primary?: boolean;
    sort_order?: number;
  }>,
) {
  return apiSend("post", `/admin/products/${id}/images`, { images });
}
