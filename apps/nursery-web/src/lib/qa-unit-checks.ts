/**
 * Lightweight unit checks for QA auth/password + checkout/payment helpers.
 * Run: npx --yes tsx src/lib/qa-unit-checks.ts
 */
import { sanitizeNext } from "./auth-redirect";
import {
  AuthApiError,
  authRateLimitMessage,
  authUserMessage,
  formatAuthCountdown,
  parseRetryAfterSeconds,
} from "./auth-messages";
import {
  isPasswordValid,
  passwordRequirementErrors,
} from "./password-rules";
import {
  canPlaceOrder,
  checkoutPayableTotal,
  isProductionSite,
  localStubPayment,
} from "./razorpay";
import {
  nextSearchGeneration,
  shouldApplySearchResult,
} from "./search-suggestions";
import {
  nextPreviewGeneration,
  shouldApplyPreviewResult,
} from "./checkout-preview";
import {
  mapPaymentApiStatus,
  normalizeUpiInitiateMode,
  shouldContinueUpiPoll,
  shouldOpenRazorpayCheckout,
  shouldShowUpiIntent,
  shouldShowUpiQr,
  upiStatusLabel,
} from "./upi-payment";

function assert(cond: unknown, msg: string): void {
  if (!cond) throw new Error(msg);
}

assert(
  authUserMessage(new Error("Invalid email or password")) ===
    "Invalid email or password.",
  "credentials mapping",
);
assert(
  authUserMessage(new Error("Account is blocked")).includes("unavailable"),
  "blocked mapping",
);
assert(
  authUserMessage(new Error("Network Error")).includes("Unable to connect"),
  "network mapping",
);
assert(
  authUserMessage(new Error("Too Many Attempts.")).toLowerCase().includes("minute"),
  "429 mapping",
);
assert(formatAuthCountdown(65) === "1:05", "countdown mm:ss");
assert(parseRetryAfterSeconds("45") === 45, "retry-after seconds");
assert(
  authUserMessage(new AuthApiError("x", { statusCode: 429, retryAfterSeconds: 45 })).includes(
    "0:45",
  ),
  "429 countdown message",
);
assert(authRateLimitMessage(90).includes("1:30"), "rate limit copy");

assert(!isPasswordValid("short"), "reject short");
assert(!isPasswordValid("alllowercase1"), "reject no upper");
assert(!isPasswordValid("ALLUPPERCASE1"), "reject no lower");
assert(!isPasswordValid("NoDigitsHere"), "reject no digit");
assert(isPasswordValid("Secret@123"), "accept strong");
assert(passwordRequirementErrors("abc").length >= 1, "errors list");

// QA-CHK-001 — payable only from preview
assert(checkoutPayableTotal(null) === null, "null preview");
assert(checkoutPayableTotal({}) === null, "empty preview");
assert(checkoutPayableTotal({ grand_total: 722 }) === 722, "preview total");
assert(
  canPlaceOrder({ previewReady: false, busy: false }) === false,
  "block without preview",
);
assert(
  canPlaceOrder({ previewReady: true, busy: true }) === false,
  "block while busy",
);
assert(
  canPlaceOrder({ previewReady: true, busy: false, previewLoading: true }) ===
    false,
  "block while preview loading",
);
assert(
  canPlaceOrder({ previewReady: true, busy: false }) === true,
  "allow when ready",
);

// QA-PAY-001 — production stub refusal (isProductionSite depends on env at runtime)
if (isProductionSite()) {
  let threw = false;
  try {
    localStubPayment("order_x", 1);
  } catch {
    threw = true;
  }
  assert(threw, "production must refuse local_stub");
} else {
  const stub = localStubPayment("order_x", 1);
  assert(stub.razorpay_signature.startsWith("local_"), "dev stub signature");
}

// QA-06-001 — stale search response must not apply
assert(nextSearchGeneration(0) === 1, "gen bump");
assert(shouldApplySearchResult(3, 3) === true, "same gen applies");
assert(shouldApplySearchResult(2, 3) === false, "stale gen rejected");

// QA-PERF-011 — stale checkout preview must not apply
assert(nextPreviewGeneration(4) === 5, "preview gen bump");
assert(shouldApplyPreviewResult(5, 5) === true, "preview same gen applies");
assert(shouldApplyPreviewResult(4, 5) === false, "preview stale gen rejected");

// QA-11 — redirect sanitize (session-clear handler lives in api.ts / auth store)
assert(sanitizeNext("https://evil.example") === "/account", "reject absolute");
assert(sanitizeNext("//evil.example") === "/account", "reject protocol-relative");
assert(sanitizeNext("/checkout") === "/checkout", "allow relative");

// QA-21 — UPI polling / status mapping
assert(mapPaymentApiStatus("success") === "paid", "upi success→paid");
assert(mapPaymentApiStatus("failed") === "failed", "upi failed");
assert(mapPaymentApiStatus("pending") === "pending", "upi pending");
assert(upiStatusLabel("polling").includes("Waiting"), "upi polling label");
assert(
  shouldContinueUpiPoll({
    status: "pending",
    startedAtMs: 0,
    nowMs: 1000,
    maxMs: 5000,
  }) === true,
  "upi poll continue",
);
assert(
  shouldContinueUpiPoll({
    status: "paid",
    startedAtMs: 0,
    nowMs: 1000,
    maxMs: 5000,
  }) === false,
  "upi poll stop on paid",
);
assert(
  shouldContinueUpiPoll({
    status: "pending",
    startedAtMs: 0,
    nowMs: 10_000,
    maxMs: 5000,
  }) === false,
  "upi poll stop on timeout",
);

// QA-25 — UPI/Razorpay checkout presentation helpers
assert(normalizeUpiInitiateMode("checkout") === "checkout", "mode checkout");
assert(normalizeUpiInitiateMode("dynamic_qr") === "dynamic_qr", "mode qr");
assert(normalizeUpiInitiateMode("bogus") === "checkout", "mode default checkout");
assert(
  shouldOpenRazorpayCheckout({
    key: "rzp_test_x",
    order_id: "order_abc",
    amount: 100,
    currency: "INR",
    upi_mode: "checkout",
  }) === true,
  "open checkout when mode checkout",
);
assert(
  shouldOpenRazorpayCheckout({
    key: "rzp_test_x",
    order_id: "order_abc",
    amount: 100,
    currency: "INR",
    mode: "local_stub",
    upi_mode: "checkout",
  }) === false,
  "never open checkout for stub",
);
assert(
  shouldShowUpiQr({
    order_id: "o",
    amount: 1,
    currency: "INR",
    qr_data: "upi://pay",
  }) === true,
  "show qr when qr_data",
);
assert(
  shouldShowUpiIntent({
    order_id: "o",
    amount: 1,
    currency: "INR",
    upi_intent_url: "upi://pay",
  }) === true,
  "show intent when url",
);

import { notificationHref } from "./notification-deep-link";
assert(
  notificationHref({ route: "/account/orders/12", order_id: 12 }) === "/account/orders/12",
  "deep link prefers route",
);
assert(notificationHref({ order_id: 9 }) === "/account/orders/9", "deep link order");
assert(notificationHref({ return_id: 3 }) === "/account/returns/3", "deep link return");
assert(notificationHref({ route: "https://evil" }) === null, "reject absolute route");
// QA-36-005: customer bare /returns/* remaps to /account/returns/*
assert(
  notificationHref({ route: "/returns/88", return_id: 88 }) === "/account/returns/88",
  "customer remaps /returns route",
);
assert(
  notificationHref({ route: "/returns/88" }, "admin") === "/returns/88",
  "admin keeps /returns route",
);

import { orderStatusLabel, orderStatusTone } from "./order-status";
assert(orderStatusLabel("PENDING_PAYMENT") === "Order placed", "list/detail PENDING label");
assert(orderStatusLabel("OUT_FOR_DELIVERY") === "Out for delivery", "OFD label");
assert(orderStatusTone("PAYMENT_FAILED") === "error", "failed tone");

// QA-33 — HttpOnly cookie policy + no JS-accessible JWT storage
import {
  BROWSER_API_BASE,
  BROWSER_AUTH_BASE,
  customerAccessCookiePolicy,
  customerCsrfCookiePolicy,
  customerRefreshCookiePolicy,
  resolveCookieSecureFlag,
  stripAuthTokens,
} from "./session-cookie-policy";
import { storage as webStorage } from "./storage";
import { resolveBrowserApiBaseUrl } from "./api-base";

const accessPol = customerAccessCookiePolicy(true);
assert(accessPol.httpOnly === true, "access httpOnly");
assert(accessPol.secure === true, "access secure in prod");
assert(accessPol.sameSite === "lax", "access SameSite=Lax");
const refreshPol = customerRefreshCookiePolicy(false);
assert(refreshPol.httpOnly === true, "refresh httpOnly");
assert(refreshPol.path === "/api/bff", "refresh path narrowed to BFF");
assert(refreshPol.secure === false, "refresh secure false on local http");
const csrfPol = customerCsrfCookiePolicy(true);
assert(csrfPol.httpOnly === false, "csrf readable for double-submit");
assert(
  !("access_token" in stripAuthTokens({ access_token: "x", refresh_token: "y", user: { id: 1 } })),
  "strip access_token",
);
assert(webStorage.getAccess() === null, "JS cannot read access token");
assert(webStorage.getRefresh() === null, "JS cannot read refresh token");
assert(resolveBrowserApiBaseUrl() === "/api/bff/proxy", "browser uses BFF proxy");

// QA-34 — HTTPS/staging Secure cookie contract + BFF-only browser base
assert(BROWSER_API_BASE === "/api/bff/proxy", "browser API base constant");
assert(BROWSER_AUTH_BASE === "/api/bff/auth", "browser auth base constant");
assert(
  resolveCookieSecureFlag({ nodeEnv: "production" }) === true,
  "prod Secure cookies default on",
);
assert(
  resolveCookieSecureFlag({ nodeEnv: "development" }) === false,
  "local Secure cookies default off",
);
assert(
  resolveCookieSecureFlag({ nodeEnv: "development", cookieSecureEnv: "true" }) ===
    true,
  "COOKIE_SECURE=true forces Secure",
);
assert(
  resolveCookieSecureFlag({ nodeEnv: "production", cookieSecureEnv: "false" }) ===
    false,
  "COOKIE_SECURE=false override",
);

// QA-35 — payment status labels
import { paymentStatusLabel } from "./payment-status";
assert(paymentStatusLabel("success") === "Paid", "payment success→Paid");
assert(paymentStatusLabel("refund_pending") === "Refund pending", "refund pending");
assert(orderStatusLabel("PENDING_PAYMENT") === "Order placed", "customer pending copy");

// QA-37-004 — cart store distinguishes fetch error from empty cart shape
import { __cartEmptyFallback } from "../store/cart";
assert(__cartEmptyFallback.items.length === 0, "empty cart fallback shape");
assert(__cartEmptyFallback.item_count === 0, "empty cart item_count");

// QA-37 network resilience — shopper-safe error map
import {
  classifyHttpStatus,
  classifyTransport,
  isTransientAxiosFailure,
  sanitizeTechnical,
} from "./network-errors";
assert(classifyHttpStatus(503).kind === "apiUnavailable", "503 unavailable");
assert(classifyHttpStatus(401).kind === "authExpired", "401 auth");
assert(
  classifyTransport({ browserOffline: true }).kind === "offline",
  "browser offline",
);
assert(
  classifyTransport({ timedOut: true }).userMessage.toLowerCase().includes("long"),
  "timeout copy",
);
assert(isTransientAxiosFailure({ code: "ERR_NETWORK" }) === true, "transient network");
assert(
  sanitizeTechnical("Confirm API_PROXY_TARGET and php artisan serve").includes("offline"),
  "hide ops toast",
);

console.log("qa-unit-checks: PASS");
