export type RazorpayCheckoutOptions = {
  key: string;
  amount: number;
  currency: string;
  name?: string;
  description?: string;
  order_id: string;
  prefill?: { name?: string; email?: string; contact?: string };
  notes?: Record<string, string>;
  /** Prefer methods returned by the API (e.g. UPI-only). */
  method?: Record<string, boolean>;
  theme?: { color?: string };
  handler: (response: RazorpaySuccessResponse) => void;
  modal?: { ondismiss?: () => void };
};

export type RazorpaySuccessResponse = {
  razorpay_payment_id: string;
  razorpay_order_id: string;
  razorpay_signature: string;
};

type RazorpayConstructor = new (options: RazorpayCheckoutOptions) => { open: () => void };

declare global {
  interface Window {
    Razorpay?: RazorpayConstructor;
  }
}

let loading: Promise<void> | null = null;

export function loadRazorpayScript(): Promise<void> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("Razorpay requires a browser"));
  }
  if (window.Razorpay) return Promise.resolve();
  if (loading) return loading;

  loading = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://checkout.razorpay.com/v1/checkout.js";
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => {
      loading = null;
      reject(new Error("Unable to load Razorpay Checkout"));
    };
    document.body.appendChild(script);
  });

  return loading;
}

/** Open Razorpay Checkout; resolves with gateway ids or rejects on cancel/error. */
export async function openRazorpayCheckout(
  options: Omit<RazorpayCheckoutOptions, "handler" | "modal">,
): Promise<RazorpaySuccessResponse> {
  await loadRazorpayScript();
  const Rzp = window.Razorpay;
  if (!Rzp) throw new Error("Razorpay Checkout is unavailable");

  return new Promise((resolve, reject) => {
    const rzp = new Rzp({
      ...options,
      theme: { color: "#1f6b4a", ...(options.theme ?? {}) },
      handler: (response) => resolve(response),
      modal: {
        ondismiss: () => reject(new Error("Payment cancelled")),
      },
    });
    rzp.open();
  });
}

/** True when the site build / deploy is production-tier. */
export function isProductionSite(): boolean {
  return (
    process.env.NODE_ENV === "production" ||
    process.env.NEXT_PUBLIC_SITE_ENV === "production"
  );
}

/** Dev-only stub when API returns mode=local_stub (no real keys). Never in production. */
export function localStubPayment(providerOrderId: string, paymentId: number): RazorpaySuccessResponse {
  if (isProductionSite()) {
    throw new Error(
      "Test payment mode is not available in production. Please use a configured payment method or cash on delivery.",
    );
  }
  return {
    razorpay_order_id: providerOrderId,
    razorpay_payment_id: `local_${paymentId}_${Date.now()}`,
    razorpay_signature: `local_${providerOrderId}`,
  };
}

/** Pure helpers for checkout payable display (QA-CHK-001). */
export function checkoutPayableTotal(
  preview: { grand_total?: number | null } | null | undefined,
): number | null {
  if (preview?.grand_total == null || Number.isNaN(Number(preview.grand_total))) {
    return null;
  }
  return Number(preview.grand_total);
}

export function canPlaceOrder(opts: {
  previewReady: boolean;
  busy: boolean;
  previewLoading?: boolean;
}): boolean {
  return opts.previewReady && !opts.busy && !opts.previewLoading;
}
