/** Canonical customer/admin-facing payment status labels (QA-35). */
export const PAYMENT_STATUS_LABELS: Record<string, string> = {
  success: "Paid",
  pending: "Pending",
  failed: "Failed",
  refund_pending: "Refund pending",
  refunded: "Refunded",
  cod: "COD",
};

export function paymentStatusLabel(status: string | null | undefined): string {
  if (!status) return "—";
  const key = status.trim().toLowerCase();
  return PAYMENT_STATUS_LABELS[key] ?? status.replaceAll("_", " ");
}
