/**
 * QA-08 Admin Web unit checks.
 * Run: npm run test:unit
 */
import {
  hasAllPermissions,
  hasPermission,
  isStaffUser,
  isSuperAdmin,
  permissionDeniedMessage,
} from "./auth/permissions";
import { sanitizeAdminNext } from "./auth-redirect";
import {
  assertLocalStubRefundAllowed,
  canOfferLocalStubRefund,
  isAdminProductionSite,
} from "./refund-safety";
import { NAV_SECTIONS, visibleNav } from "./auth/navigation";

function assert(cond: unknown, msg: string): void {
  if (!cond) throw new Error(msg);
}

const admin = {
  id: 1,
  name: "Admin",
  email: "a@test",
  roles: ["admin"],
  permissions: ["users.manage", "orders.view", "payments.refund"],
};

const support = {
  id: 2,
  name: "Support",
  email: "s@test",
  roles: ["customer_support"],
  permissions: ["orders.view", "customers.view"],
};

const superA = {
  id: 3,
  name: "Super",
  email: "su@test",
  roles: ["super_admin"],
  permissions: [] as string[],
};

assert(isStaffUser(admin), "admin staff");
assert(isSuperAdmin(superA), "super");
assert(hasPermission(admin, "users.manage"), "admin users.manage");
assert(!hasPermission(support, "users.manage"), "support no users.manage");
assert(hasPermission(superA, "users.manage"), "super bypass");
assert(hasAllPermissions(admin, ["users.manage", "orders.view"]), "all perms");
assert(!hasAllPermissions(support, ["users.manage", "orders.view"]), "missing all");
assert(
  permissionDeniedMessage("users.manage").includes("users.manage"),
  "denied message",
);

const navAdmin = visibleNav(admin);
assert(
  navAdmin.some((s) => s.items.some((i) => i.href === "/users")),
  "users nav visible for admin",
);
const navSupport = visibleNav(support);
assert(
  !navSupport.some((s) => s.items.some((i) => i.href === "/users")),
  "users nav hidden for support",
);
assert(
  NAV_SECTIONS.some((s) =>
    s.items.some((i) => i.href === "/users" && !i.href.includes("coming-soon")),
  ),
  "users route not coming-soon",
);

assert(typeof isAdminProductionSite() === "boolean", "prod flag");
if (isAdminProductionSite()) {
  assert(!canOfferLocalStubRefund(), "prod no stub refund UI");
  let threw = false;
  try {
    assertLocalStubRefundAllowed();
  } catch {
    threw = true;
  }
  assert(threw, "prod assert throws");
} else {
  assert(canOfferLocalStubRefund(), "non-prod stub refund UI allowed");
  assertLocalStubRefundAllowed();
}

// QA-11 — open-redirect sanitization
assert(sanitizeAdminNext("https://evil.example") === "/dashboard", "reject absolute");
assert(sanitizeAdminNext("//evil.example") === "/dashboard", "reject protocol-relative");
assert(sanitizeAdminNext("/orders") === "/orders", "allow relative");
assert(sanitizeAdminNext("/login") === "/dashboard", "reject login loop");

import { adminNotificationHref } from "./notification-deep-link";
assert(adminNotificationHref({ route: "/orders/4" }) === "/orders/4", "admin deep link route");
assert(adminNotificationHref({ order_id: 7 }) === "/orders/7", "admin deep link order");

// QA-32 — canonical admin order status labels
import { orderStatusLabel } from "./auth/order-transitions";
assert(orderStatusLabel("PENDING_PAYMENT") === "Pending payment", "admin pending label");
assert(orderStatusLabel("OUT_FOR_DELIVERY") === "Out for delivery", "admin OFD label");
assert(orderStatusLabel("RETURN_REQUESTED") === "Return requested", "admin return label");
assert(orderStatusLabel(null) === "Unknown", "admin null status");

// QA-33 — HttpOnly cookie policy + no JS-accessible JWT storage
import {
  BROWSER_API_BASE,
  adminAccessCookiePolicy,
  adminCsrfCookiePolicy,
  adminRefreshCookiePolicy,
  resolveCookieSecureFlag,
  stripAuthTokens,
} from "./session-cookie-policy";
import { storage as adminStorage } from "./storage";

const aPol = adminAccessCookiePolicy(true);
assert(aPol.httpOnly === true, "admin access httpOnly");
assert(aPol.secure === true, "admin access secure prod");
assert(aPol.sameSite === "lax", "admin access SameSite");
assert(adminRefreshCookiePolicy(false).path === "/api/bff", "admin refresh path");
assert(adminCsrfCookiePolicy(true).httpOnly === false, "admin csrf readable");
assert(
  !("refresh_token" in stripAuthTokens({ access_token: "a", refresh_token: "b", user: {} })),
  "admin strip tokens",
);
assert(adminStorage.getAccess() === null, "admin JS cannot read access");
assert(adminStorage.getRefresh() === null, "admin JS cannot read refresh");

// QA-34 — Secure cookie contract
assert(BROWSER_API_BASE === "/api/bff/proxy", "admin browser API base");
assert(resolveCookieSecureFlag({ nodeEnv: "production" }) === true, "admin prod Secure");
assert(
  resolveCookieSecureFlag({ nodeEnv: "development", cookieSecureEnv: "true" }) ===
    true,
  "admin COOKIE_SECURE override",
);

// QA-35 — payment + transition labels
import { paymentStatusLabel } from "./payment-status";
assert(paymentStatusLabel("success") === "Paid", "admin payment Paid");
assert(paymentStatusLabel("failed") === "Failed", "admin payment Failed");
assert(orderStatusLabel("OUT_FOR_DELIVERY") === "Out for delivery", "transition label");

import { GREENLEAF_SUPPORT_PHONE, looksLikeErrorReference, newErrorReference } from "./support";
assert(GREENLEAF_SUPPORT_PHONE === "8926627220", "helpline");
assert(looksLikeErrorReference(newErrorReference()), "GL reference");

console.log("qa-unit-checks: OK");
