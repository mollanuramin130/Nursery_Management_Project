/**
 * Pure cookie policy for Admin Web (QA-33) — safe to unit-test without Next runtime.
 */
export const ADMIN_ACCESS_COOKIE = "gl_admin_web_access";
export const ADMIN_REFRESH_COOKIE = "gl_admin_web_refresh";
export const ADMIN_CSRF_COOKIE = "gl_admin_web_csrf";
export const LEGACY_ADMIN_ACCESS = "gl_admin_access";
export const LEGACY_ADMIN_REFRESH = "gl_admin_refresh";

export function adminAccessCookiePolicy(isProduction: boolean) {
  return {
    httpOnly: true,
    secure: isProduction,
    sameSite: "lax" as const,
    path: "/",
  };
}

export function adminRefreshCookiePolicy(isProduction: boolean) {
  return {
    httpOnly: true,
    secure: isProduction,
    sameSite: "lax" as const,
    path: "/api/bff",
  };
}

export function adminCsrfCookiePolicy(isProduction: boolean) {
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

/** QA-34 — production/staging cookie Secure resolution. */
export function resolveCookieSecureFlag(opts: {
  nodeEnv?: string;
  cookieSecureEnv?: string | undefined;
}): boolean {
  if (opts.cookieSecureEnv === "true") return true;
  if (opts.cookieSecureEnv === "false") return false;
  return opts.nodeEnv === "production";
}

export const BROWSER_API_BASE = "/api/bff/proxy";
export const BROWSER_AUTH_BASE = "/api/bff/auth";
