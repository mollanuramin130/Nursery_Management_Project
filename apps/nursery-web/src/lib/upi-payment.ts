/**
 * UPI checkout helpers (QA-21 / QA-25).
 * Server remains authoritative — UI never invents PAID.
 */

export type UpiInitiateMode = "dynamic_qr" | "upi_intent" | "checkout";

export type UpiClientPayload = {
  key?: string;
  order_id: string;
  amount: number;
  currency: string;
  name?: string;
  mode?: string;
  channel?: string;
  upi_mode?: UpiInitiateMode | string;
  qr_data?: string;
  qr_image_url?: string | null;
  upi_intent_url?: string;
  expires_at?: string | null;
  stub_confirm_allowed?: boolean;
  prefill?: { name?: string; email?: string; contact?: string };
  method?: Record<string, boolean>;
};

/** Modes the Customer Web may request on POST /payments/initiate. */
export const WEB_UPI_INITIATE_MODES: readonly UpiInitiateMode[] = [
  "checkout",
  "dynamic_qr",
  "upi_intent",
] as const;

export function normalizeUpiInitiateMode(
  value: string | null | undefined,
): UpiInitiateMode {
  if (value === "dynamic_qr" || value === "upi_intent" || value === "checkout") {
    return value;
  }
  return "checkout";
}

/**
 * Open hosted Razorpay Checkout when the API returned checkout mode
 * (or an equivalent payload with a real key + order id, not local_stub).
 */
export function shouldOpenRazorpayCheckout(payload: UpiClientPayload): boolean {
  if ((payload.mode ?? "") === "local_stub") return false;
  const key = (payload.key ?? "").trim();
  const orderId = (payload.order_id ?? "").trim();
  if (!key || !orderId) return false;
  const mode = (payload.upi_mode ?? "").toLowerCase();
  return mode === "checkout" || mode === "";
}

/** Show QR wait UI when the API provided QR data/image. */
export function shouldShowUpiQr(payload: UpiClientPayload): boolean {
  return Boolean(payload.qr_image_url || payload.qr_data);
}

/** Show UPI intent deep-link when the API provided an intent URL. */
export function shouldShowUpiIntent(payload: UpiClientPayload): boolean {
  return Boolean((payload.upi_intent_url ?? "").trim());
}

export type UpiPaymentUiStatus =
  | "pending"
  | "polling"
  | "paid"
  | "failed"
  | "expired"
  | "cancelled";

/** Bound polling: every intervalMs, stop after maxMs or terminal status. */
export function shouldContinueUpiPoll(opts: {
  status: UpiPaymentUiStatus;
  startedAtMs: number;
  nowMs: number;
  maxMs?: number;
}): boolean {
  const maxMs = opts.maxMs ?? 3 * 60 * 1000;
  if (["paid", "failed", "expired", "cancelled"].includes(opts.status)) {
    return false;
  }
  return opts.nowMs - opts.startedAtMs < maxMs;
}

export function mapPaymentApiStatus(paymentStatus: string | null | undefined): UpiPaymentUiStatus {
  const s = (paymentStatus ?? "").toLowerCase();
  if (s === "success" || s === "paid" || s === "captured") return "paid";
  if (s === "failed") return "failed";
  if (s === "cancelled" || s === "canceled") return "cancelled";
  if (s === "expired") return "expired";
  return "pending";
}

export function qrImageSrc(payload: UpiClientPayload): string | null {
  if (payload.qr_image_url) return payload.qr_image_url;
  if (payload.qr_data) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload.qr_data)}`;
  }
  return null;
}

export function upiStatusLabel(status: UpiPaymentUiStatus): string {
  switch (status) {
    case "paid":
      return "Paid";
    case "failed":
      return "Failed";
    case "expired":
      return "Expired";
    case "cancelled":
      return "Cancelled";
    case "polling":
      return "Waiting for payment…";
    default:
      return "Pending";
  }
}
