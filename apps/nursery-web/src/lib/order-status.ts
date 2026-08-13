/**
 * Shared customer-facing order status labels (list + detail).
 * Canonical copy: "Order placed" for PENDING_PAYMENT (not "Payment pending").
 */

export const ORDER_STATUS_LABELS: Record<string, string> = {
  PENDING_PAYMENT: "Order placed",
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

export function orderStatusTone(
  status: string,
): "brand" | "success" | "warning" | "error" | "neutral" {
  if (status === "DELIVERED" || status === "REFUNDED" || status === "CONFIRMED") {
    return "success";
  }
  if (
    status === "CANCELLED" ||
    status === "PAYMENT_FAILED" ||
    status === "DELIVERY_FAILED"
  ) {
    return "error";
  }
  if (status === "PENDING_PAYMENT" || status === "RETURN_REQUESTED") {
    return "warning";
  }
  return "brand";
}
