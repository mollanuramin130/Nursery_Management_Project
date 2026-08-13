/**
 * QA-33 — HttpOnly session cookies for Admin Web BFF.
 */
import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { resolveCookieSecureFlag } from "@/lib/session-cookie-policy";

export const ACCESS_COOKIE = "gl_admin_web_access";
export const REFRESH_COOKIE = "gl_admin_web_refresh";
export const CSRF_COOKIE = "gl_admin_web_csrf";
export const CSRF_HEADER = "x-csrf-token";

export const LEGACY_ACCESS_KEY = "gl_admin_access";
export const LEGACY_REFRESH_KEY = "gl_admin_refresh";

const ACCESS_MAX_AGE = 60 * 60;
const REFRESH_MAX_AGE = 90 * 24 * 60 * 60;

export function upstreamApiBase(): string {
  return (
    process.env.API_PROXY_TARGET ??
    process.env.LARAVEL_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "http://127.0.0.1:8000/api/v1"
  ).replace(/\/$/, "");
}

function cookieSecure(): boolean {
  return resolveCookieSecureFlag({
    nodeEnv: process.env.NODE_ENV,
    cookieSecureEnv: process.env.COOKIE_SECURE,
  });
}

export function accessCookieOptions(maxAge = ACCESS_MAX_AGE) {
  return {
    httpOnly: true,
    secure: cookieSecure(),
    sameSite: "lax" as const,
    path: "/",
    maxAge,
  };
}

export function refreshCookieOptions(maxAge = REFRESH_MAX_AGE) {
  return {
    httpOnly: true,
    secure: cookieSecure(),
    sameSite: "lax" as const,
    path: "/api/bff",
    maxAge,
  };
}

export function csrfCookieOptions(maxAge = REFRESH_MAX_AGE) {
  return {
    httpOnly: false,
    secure: cookieSecure(),
    sameSite: "lax" as const,
    path: "/",
    maxAge,
  };
}

export function setAuthCookies(
  res: NextResponse,
  accessToken: string,
  refreshToken: string,
) {
  res.cookies.set(ACCESS_COOKIE, accessToken, accessCookieOptions());
  res.cookies.set(REFRESH_COOKIE, refreshToken, refreshCookieOptions());
}

export function clearAuthCookies(res: NextResponse) {
  res.cookies.set(ACCESS_COOKIE, "", { ...accessCookieOptions(0), maxAge: 0 });
  res.cookies.set(REFRESH_COOKIE, "", { ...refreshCookieOptions(0), maxAge: 0 });
}

export function ensureCsrfToken(res: NextResponse, existing?: string | null): string {
  const token =
    existing && existing.length >= 16
      ? existing
      : typeof crypto !== "undefined" && "randomUUID" in crypto
        ? crypto.randomUUID().replace(/-/g, "")
        : `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
  res.cookies.set(CSRF_COOKIE, token, csrfCookieOptions());
  return token;
}

export async function readSessionCookies(): Promise<{
  access: string | null;
  refresh: string | null;
  csrf: string | null;
}> {
  const jar = await cookies();
  return {
    access: jar.get(ACCESS_COOKIE)?.value ?? null,
    refresh: jar.get(REFRESH_COOKIE)?.value ?? null,
    csrf: jar.get(CSRF_COOKIE)?.value ?? null,
  };
}

export function csrfHeaderFrom(req: Request): string | null {
  return req.headers.get(CSRF_HEADER) ?? req.headers.get("X-CSRF-Token");
}

export function assertCsrf(req: Request, csrfCookie: string | null): NextResponse | null {
  const method = req.method.toUpperCase();
  if (method === "GET" || method === "HEAD" || method === "OPTIONS") return null;
  const header = csrfHeaderFrom(req);
  if (!csrfCookie || !header || header !== csrfCookie) {
    return NextResponse.json(
      {
        success: false,
        message: "CSRF token mismatch",
        data: null,
        errors: null,
        meta: { error_code: "CSRF_MISMATCH" },
      },
      { status: 403 },
    );
  }
  return null;
}

export function stripTokensFromAuthData(data: Record<string, unknown>): Record<string, unknown> {
  const copy = { ...data };
  delete copy.access_token;
  delete copy.refresh_token;
  delete copy.token;
  return copy;
}
