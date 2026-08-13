/** Mirrors backend OrderStateMachine::TRANSITIONS for UX only. Backend remains authoritative. */
export const ORDER_TRANSITIONS: Record<string, string[]> = {
  PENDING_PAYMENT: ["CONFIRMED", "PAYMENT_FAILED", "CANCELLED"],
  PAYMENT_FAILED: ["PENDING_PAYMENT", "CANCELLED"],
  CONFIRMED: ["PROCESSING", "CANCELLED"],
  PROCESSING: ["PACKED", "CANCELLED"],
  PACKED: ["SHIPPED", "CANCELLED"],
  SHIPPED: ["OUT_FOR_DELIVERY", "DELIVERED"],
  OUT_FOR_DELIVERY: ["DELIVERED", "DELIVERY_FAILED"],
  DELIVERY_FAILED: ["OUT_FOR_DELIVERY", "DELIVERED"],
  DELIVERED: ["RETURN_REQUESTED"],
  RETURN_REQUESTED: ["RETURNED", "REFUNDED", "DELIVERED"],
  RETURNED: ["REFUNDED"],
  CANCELLED: [],
  REFUNDED: [],
};

export function allowedOrderTransitions(status: string): string[] {
  return ORDER_TRANSITIONS[status] ?? [];
}

export const ORDER_STATUSES = Object.keys(ORDER_TRANSITIONS);

/** Canonical admin-facing order status labels (QA-32 UI uniformity). */
export const ORDER_STATUS_LABELS: Record<string, string> = {
  PENDING_PAYMENT: "Pending payment",
  PAYMENT_FAILED: "Payment failed",
  CONFIRMED: "Confirmed",
  PROCESSING: "Processing",
  PACKED: "Packed",
  SHIPPED: "Shipped",
  OUT_FOR_DELIVERY: "Out for delivery",
  DELIVERED: "Delivered",
  CANCELLED: "Cancelled",
  RETURN_REQUESTED: "Return requested",
  RETURNED: "Returned",
  REFUNDED: "Refunded",
  DELIVERY_FAILED: "Delivery failed",
};

export function orderStatusLabel(status: string | null | undefined): string {
  if (!status) return "Unknown";
  return ORDER_STATUS_LABELS[status] ?? status.replaceAll("_", " ");
}

export function statusTone(status: string): "neutral" | "success" | "warning" | "danger" | "info" {
  const s = status.toUpperCase();
  if (["DELIVERED", "REFUNDED", "CONFIRMED"].includes(s)) return "success";
  if (["CANCELLED", "PAYMENT_FAILED", "DELIVERY_FAILED"].includes(s)) return "danger";
  if (["PENDING_PAYMENT", "RETURN_REQUESTED"].includes(s)) return "warning";
  if (["SHIPPED", "OUT_FOR_DELIVERY", "PROCESSING", "PACKED"].includes(s)) return "info";
  return "neutral";
}
