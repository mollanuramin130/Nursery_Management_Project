/**
 * Pure cookie policy for Customer Web (QA-33) — safe to unit-test without Next runtime.
 */
export const CUSTOMER_ACCESS_COOKIE = "gl_web_access";
export const CUSTOMER_REFRESH_COOKIE = "gl_web_refresh";
export const CUSTOMER_CSRF_COOKIE = "gl_web_csrf";
export const LEGACY_CUSTOMER_ACCESS = "gl_access_token";
export const LEGACY_CUSTOMER_REFRESH = "gl_refresh_token";

export function customerAccessCookiePolicy(isProduction: boolean) {
  return {
    httpOnly: true,
    secure: isProduction,
    sameSite: "lax" as const,
    path: "/",
  };
}

export function customerRefreshCookiePolicy(isProduction: boolean) {
  return {
    httpOnly: true,
    secure: isProduction,
    sameSite: "lax" as const,
    path: "/api/bff",
  };
}

export function customerCsrfCookiePolicy(isProduction: boolean) {
  return {
    httpOnly: false,
    secure: isProduction,
    sameSite: "lax" as const,
    path: "/",
  };
}

export function stripAuthTokens<T extends Record<string, unknown>>(data: T): Omit<
  T,
  "access_token" | "refresh_token" | "token"
> {
  const copy = { ...data };
  delete copy.access_token;
  delete copy.refresh_token;
  delete copy.token;
  return copy;
}

/** QA-34 — production/staging cookie Secure resolution (mirrors server cookieSecure). */
export function resolveCookieSecureFlag(opts: {
  nodeEnv?: string;
  cookieSecureEnv?: string | undefined;
}): boolean {
  if (opts.cookieSecureEnv === "true") return true;
  if (opts.cookieSecureEnv === "false") return false;
  return opts.nodeEnv === "production";
}

/** QA-34 — browser must never target Laravel directly for auth/API axios. */
export const BROWSER_API_BASE = "/api/bff/proxy";
export const BROWSER_AUTH_BASE = "/api/bff/auth";
