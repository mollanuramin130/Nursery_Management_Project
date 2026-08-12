import { apiGet, apiSend } from "@/lib/api/client";

export type AdminSupplier = {
  id: number;
  code: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  city?: string | null;
  state?: string | null;
  contact_person?: string | null;
  gstin?: string | null;
  status: string;
};

export type SupplierWritePayload = {
  code: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  city?: string | null;
  state?: string | null;
  contact_person?: string | null;
  gstin?: string | null;
  status?: string;
};

export type PurchaseOrderListRow = {
  id: number;
  po_number: string;
  supplier?: string | null;
  supplier_id: number;
  warehouse_id?: number | null;
  status: string;
  item_count: number;
  units_ordered: number;
  units_received: number;
  grand_total: number;
  expected_at?: string | null;
  received_at?: string | null;
  created_at?: string | null;
};

export type PurchaseOrderItem = {
  id: number;
  product_id: number;
  product_name?: string | null;
  sku?: string | null;
  product_variant_id?: number | null;
  quantity_ordered: number;
  quantity_received: number;
  quantity_remaining: number;
  unit_cost: number;
  line_total: number;
};

export type PurchaseOrderDetail = {
  id: number;
  po_number: string;
  supplier_id: number;
  supplier?: string | null;
  warehouse_id?: number | null;
  status: string;
  subtotal: number;
  tax_total: number;
  grand_total: number;
  expected_at?: string | null;
  received_at?: string | null;
  created_at?: string | null;
  items: PurchaseOrderItem[];
  actions: {
    can_receive: boolean;
    can_cancel: boolean;
    can_approve: boolean;
  };
};

export async function fetchSuppliers() {
  return apiGet<AdminSupplier[]>("/admin/suppliers");
}

export async function createSupplier(payload: SupplierWritePayload) {
  return apiSend<{ id: number; code: string; name: string }>("post", "/admin/suppliers", payload);
}

export async function updateSupplier(id: number, payload: Partial<SupplierWritePayload>) {
  return apiSend<{ id: number; code: string; name: string; status: string }>(
    "put",
    `/admin/suppliers/${id}`,
    payload,
  );
}

export async function deleteSupplier(id: number) {
  return apiSend("delete", `/admin/suppliers/${id}`);
}

export async function fetchPurchaseOrders(params?: { status?: string; page?: number; per_page?: number }) {
  return apiGet<PurchaseOrderListRow[]>("/admin/purchase-orders", params as Record<string, unknown>);
}

export async function fetchPurchaseOrder(id: number) {
  return apiGet<PurchaseOrderDetail>(`/admin/purchase-orders/${id}`);
}

export async function createPurchaseOrder(payload: {
  supplier_id: number;
  warehouse_id: number;
  po_number?: string;
  tax_total?: number;
  expected_at?: string;
  status?: string;
  items: Array<{
    product_id: number;
    variant_id?: number | null;
    quantity: number;
    unit_cost: number;
  }>;
}) {
  return apiSend<PurchaseOrderDetail>("post", "/admin/purchase-orders", payload);
}

export async function approvePurchaseOrder(id: number) {
  return apiSend<PurchaseOrderDetail>("post", `/admin/purchase-orders/${id}/approve`);
}

export async function cancelPurchaseOrder(id: number) {
  return apiSend<PurchaseOrderDetail>("post", `/admin/purchase-orders/${id}/cancel`);
}

export async function receivePurchaseOrder(
  id: number,
  payload?: {
    items: Array<{
      purchase_order_item_id: number;
      quantity: number;
      damaged?: number;
    }>;
  },
) {
  return apiSend<PurchaseOrderDetail>("post", `/admin/purchase-orders/${id}/receive`, payload);
}
