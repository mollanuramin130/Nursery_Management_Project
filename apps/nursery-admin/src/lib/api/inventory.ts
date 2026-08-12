import { apiGet, apiSend } from "@/lib/api/client";
import type { InventoryRow, Pagination } from "@/lib/types";

export type InventoryItemRow = InventoryRow & {
  thumbnail_url?: string | null;
  categories?: string[];
  qty_damaged?: number;
  quantity_available?: number;
  reorder_level?: number;
  stock_status?: "IN_STOCK" | "LOW_STOCK" | "OUT_OF_STOCK";
  updated_at?: string | null;
  warehouse_name?: string | null;
};

export type StockMovement = {
  id: number;
  inventory_item_id: number;
  product_id: number;
  warehouse_id: number;
  type: string;
  qty_delta: number;
  qty_before?: number | null;
  qty_after?: number | null;
  reference_type?: string | null;
  reference_id?: number | null;
  note?: string | null;
  actor_user_id?: number | null;
  created_at?: string | null;
};

export type ReorderSuggestion = {
  inventory_item_id: number;
  product_id: number;
  product_name?: string | null;
  sku?: string | null;
  warehouse_id: number;
  warehouse_code?: string | null;
  sellable: number;
  reorder_level: number;
  suggested_reorder_qty: number;
  stock_status: string;
};

export type InventoryDashboard = {
  total_skus: number;
  total_on_hand_units: number;
  total_reserved_units: number;
  low_stock_count: number;
  out_of_stock_count: number;
  pending_purchase_orders: number;
  pending_transfers: number;
  recent_movements: StockMovement[];
  recent_adjustments: StockMovement[];
  definitions: Record<string, string>;
};

export type StockTransfer = {
  id: number;
  transfer_number: string;
  from_warehouse_id: number;
  from_warehouse_code?: string | null;
  to_warehouse_id: number;
  to_warehouse_code?: string | null;
  status: string;
  notes?: string | null;
  item_count: number;
  items: Array<{
    id: number;
    product_id: number;
    product_name?: string | null;
    sku?: string | null;
    quantity: number;
  }>;
  shipped_at?: string | null;
  completed_at?: string | null;
  created_at?: string | null;
  actions?: { can_ship: boolean; can_complete: boolean; can_cancel: boolean };
};

export async function fetchInventoryDashboard() {
  return apiGet<InventoryDashboard>("/admin/inventory/dashboard");
}

export async function fetchInventory(params?: {
  warehouse_id?: number;
  q?: string;
  low_stock?: boolean;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<InventoryItemRow[]>("/admin/inventory", params as Record<string, unknown>);
}

export async function fetchInventoryItem(id: number) {
  return apiGet<InventoryItemRow & { recent_movements?: StockMovement[] }>(`/admin/inventory/${id}`);
}

export async function fetchReorderSuggestions() {
  return apiGet<ReorderSuggestion[]>("/admin/inventory/reorder-suggestions");
}

export async function fetchDeadStock(params?: { days?: number; limit?: number }) {
  return apiGet<{ days: number; count: number; items: Array<Record<string, unknown>>; note?: string | null }>(
    "/admin/inventory/dead-stock",
    params as Record<string, unknown>,
  );
}

export async function fetchStockMovements(params?: {
  product_id?: number;
  warehouse_id?: number;
  type?: string;
  page?: number;
  per_page?: number;
  from?: string;
  to?: string;
}) {
  return apiGet<StockMovement[]>("/admin/inventory/movements", params as Record<string, unknown>);
}

export async function fetchTransfers(params?: { status?: string; page?: number; per_page?: number }) {
  return apiGet<StockTransfer[]>("/admin/inventory/transfers", params as Record<string, unknown>);
}

export async function fetchTransfer(id: number) {
  return apiGet<StockTransfer>(`/admin/inventory/transfers/${id}`);
}

export async function createTransfer(payload: {
  from_warehouse_id: number;
  to_warehouse_id: number;
  notes?: string;
  items: Array<{ product_id: number; quantity: number; variant_id?: number }>;
}) {
  return apiSend<StockTransfer>("post", "/admin/inventory/transfers", payload);
}

export async function shipTransfer(id: number) {
  return apiSend<StockTransfer>("post", `/admin/inventory/transfers/${id}/ship`);
}

export async function completeTransfer(id: number) {
  return apiSend<StockTransfer>("post", `/admin/inventory/transfers/${id}/complete`);
}

export async function cancelTransfer(id: number) {
  return apiSend<StockTransfer>("post", `/admin/inventory/transfers/${id}/cancel`);
}

export async function adjustInventory(payload: {
  warehouse_id: number;
  product_id: number;
  variant_id?: number | null;
  adjustment: number;
  reason?: string;
  note?: string;
  reference_type?: string;
  reference_id?: number;
  idempotency_key?: string;
}) {
  return apiSend<{
    product_id: number;
    warehouse_id: number;
    qty_on_hand: number;
    qty_reserved: number;
    qty_damaged: number;
    sellable: number;
    idempotent_replay?: boolean;
  }>("post", "/admin/inventory/adjust", payload);
}

export async function reconcileInventory(payload: {
  inventory_item_id: number;
  physical_qty: number;
  reason: string;
}) {
  return apiSend<{
    inventory_item_id: number;
    system_qty: number;
    physical_qty: number;
    difference: number;
    adjusted: boolean;
  }>("post", "/admin/inventory/reconcile", payload);
}

export async function updateInventoryThreshold(id: number, low_stock_threshold: number) {
  return apiSend<InventoryItemRow>("put", `/admin/inventory/${id}/threshold`, { low_stock_threshold });
}

export type { Pagination };
