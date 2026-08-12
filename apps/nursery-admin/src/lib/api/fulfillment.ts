import { apiGet, apiSend } from "@/lib/api/client";

export type FulfillmentDashboard = {
  awaiting_picking: number;
  being_picked: number;
  awaiting_packing: number;
  packed: number;
  ready_to_ship: number;
  in_transit: number;
  out_for_delivery: number;
  delivery_exceptions: number;
  delivered_today: number;
  warehouse_rule?: string;
};

export type FulfillmentQueueRow = {
  id: number;
  order_number: string;
  status: string;
  customer?: string | null;
  customer_email?: string | null;
  warehouse_id?: number | null;
  item_count: number;
  units: number;
  picking_completed?: boolean;
  has_open_exception?: boolean;
  tracking_number?: string | null;
  carrier?: string | null;
  created_at?: string | null;
  confirmed_at?: string | null;
};

export type FulfillmentOrder = {
  id: number;
  order_number: string;
  status: string;
  customer: { id?: number; name?: string | null; email?: string | null; phone?: string | null };
  shipping_address?: Record<string, unknown> | null;
  warehouse_id?: number | null;
  fulfillment: {
    picking_started_at?: string | null;
    picking_completed_at?: string | null;
    packed_at?: string | null;
    package_count?: number | null;
    weight_grams?: number | null;
    has_open_exception?: boolean;
    exceptions?: Array<{
      id: string;
      type: string;
      status: string;
      note?: string | null;
      reason?: string | null;
      order_item_id?: number;
      expected?: number;
      actual?: number;
      at?: string;
    }>;
  };
  items: Array<{
    id: number;
    product_id: number;
    name: string;
    sku: string;
    required: number;
    picked: number;
    unit_price: number;
  }>;
  shipment?: {
    id: number;
    status: string;
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    eta_date?: string | null;
    events?: Array<{
      id: number;
      status: string;
      description?: string | null;
      location?: string | null;
      event_at?: string | null;
    }>;
  } | null;
  actions: Record<string, boolean>;
};

export type ShipmentListRow = {
  id: number;
  order_id: number;
  order_number?: string | null;
  customer?: string | null;
  carrier?: string | null;
  tracking_number?: string | null;
  tracking_url?: string | null;
  status: string;
  eta_date?: string | null;
  shipped_at?: string | null;
  delivered_at?: string | null;
  created_at?: string | null;
};

export async function fetchFulfillmentDashboard() {
  return apiGet<FulfillmentDashboard>("/admin/fulfillment");
}

export async function fetchFulfillmentQueue(
  queue: string,
  params?: { q?: string; warehouse_id?: number; page?: number; per_page?: number },
) {
  return apiGet<FulfillmentQueueRow[]>(`/admin/fulfillment/queue/${queue}`, params as Record<string, unknown>);
}

export async function fetchFulfillmentOrder(id: number) {
  return apiGet<FulfillmentOrder>(`/admin/fulfillment/orders/${id}`);
}

export async function startPicking(id: number) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/pick/start`);
}

export async function updatePick(
  id: number,
  items: Array<{ order_item_id: number; picked: number }>,
) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/pick`, { items });
}

export async function recordPickException(
  id: number,
  payload: { order_item_id: number; expected: number; actual: number; note: string },
) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/pick/exception`, payload);
}

export async function completePicking(id: number) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/pick/complete`);
}

export async function packOrder(
  id: number,
  payload?: { package_count?: number; weight_grams?: number; note?: string },
) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/pack`, payload);
}

export async function shipOrder(
  id: number,
  payload?: {
    carrier?: string;
    tracking_number?: string;
    tracking_url?: string;
    eta_date?: string;
    weight_grams?: number;
    note?: string;
  },
) {
  return apiSend("post", `/admin/fulfillment/orders/${id}/ship`, payload);
}

export async function markOutForDelivery(id: number) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/out-for-delivery`);
}

export async function markDelivered(id: number) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/deliver`);
}

export async function failDelivery(id: number, reason: string, note?: string) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/fail-delivery`, {
    reason,
    note,
  });
}

export async function retryDelivery(id: number) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${id}/retry-delivery`);
}

export async function fetchShipments(params?: {
  status?: string;
  carrier?: string;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<ShipmentListRow[]>("/admin/fulfillment/shipments", params as Record<string, unknown>);
}

export async function fetchShipment(id: number) {
  return apiGet("/admin/fulfillment/shipments/" + id);
}

export async function addShipmentTracking(
  id: number,
  payload: { status: string; description?: string; location?: string; event_at?: string },
) {
  return apiSend("post", `/admin/fulfillment/shipments/${id}/tracking`, payload);
}

export async function fetchExceptions(params?: { page?: number; per_page?: number }) {
  return apiGet<FulfillmentQueueRow[]>("/admin/fulfillment/exceptions", params as Record<string, unknown>);
}

export async function resolveException(orderId: number, exceptionId: string, note?: string) {
  return apiSend<FulfillmentOrder>("post", `/admin/fulfillment/orders/${orderId}/exceptions/resolve`, {
    exception_id: exceptionId,
    note,
  });
}
