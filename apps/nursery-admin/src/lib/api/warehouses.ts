import { apiGet, apiSend } from "@/lib/api/client";

export type AdminWarehouse = {
  id: number;
  code: string;
  name: string;
  city?: string | null;
  is_default: boolean;
  status: string;
  inventory_sku_count?: number;
};

export type WarehouseWritePayload = {
  code: string;
  name: string;
  city?: string | null;
  is_default?: boolean;
  status?: string;
};

export async function fetchWarehouses() {
  return apiGet<AdminWarehouse[]>("/admin/warehouses");
}

export async function createWarehouse(payload: WarehouseWritePayload) {
  return apiSend<AdminWarehouse>("post", "/admin/warehouses", payload);
}

export async function updateWarehouse(id: number, payload: Partial<WarehouseWritePayload>) {
  return apiSend<AdminWarehouse>("put", `/admin/warehouses/${id}`, payload);
}
