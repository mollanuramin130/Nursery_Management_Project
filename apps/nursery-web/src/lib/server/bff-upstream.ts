import { NextResponse } from "next/server";
import {
  ACCESS_COOKIE,
  REFRESH_COOKIE,
  clearAuthCookies,
  setAuthCookies,
  upstreamApiBase,
} from "@/lib/server/session-cookies";

type Envelope = {
  success?: boolean;
  message?: string;
  data?: Record<string, unknown> | null;
  errors?: unknown;
  meta?: unknown;
};

export async function laravelFetch(
  path: string,
  init: RequestInit & { accessToken?: string | null } = {},
): Promise<Response> {
  const base = upstreamApiBase();
  const url = `${base}${path.startsWith("/") ? path : `/${path}`}`;
  const headers = new Headers(init.headers);
  headers.set("Accept", headers.get("Accept") ?? "application/json");
  if (init.accessToken) {
    headers.set("Authorization", `Bearer ${init.accessToken}`);
  }
  if (!headers.has("X-Platform")) headers.set("X-Platform", "web");
  if (!headers.has("X-App-Version")) headers.set("X-App-Version", "1.0.0");

  const { accessToken: _a, signal: userSignal, ...rest } = init;
  // QA-37: bound upstream wait so Next proxy cannot hang past the browser timeout.
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 30000);
  if (userSignal) {
    if (userSignal.aborted) controller.abort();
    else userSignal.addEventListener("abort", () => controller.abort(), { once: true });
  }
  try {
    return await fetch(url, {
      ...rest,
      headers,
      cache: "no-store",
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timer);
  }
}

export async function refreshUpstream(
  refreshToken: string,
): Promise<{ access: string; refresh: string } | null> {
  const res = await laravelFetch("/auth/refresh", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
  if (!res.ok) return null;
  const json = (await res.json()) as Envelope;
  const access = json.data?.access_token;
  const refresh = json.data?.refresh_token;
  if (typeof access !== "string" || typeof refresh !== "string") return null;
  return { access, refresh };
}

/** Forward Laravel response; optionally attach/rotate auth cookies. */
export async function forwardLaravelResponse(
  upstream: Response,
  opts?: {
    setTokens?: { access: string; refresh: string };
    clearTokens?: boolean;
  },
): Promise<NextResponse> {
  const contentType = upstream.headers.get("content-type") ?? "";
  const isJson = contentType.includes("application/json");

  let body: ArrayBuffer | string;
  if (isJson) {
    body = await upstream.text();
  } else {
    body = await upstream.arrayBuffer();
  }

  const res = new NextResponse(body, {
    status: upstream.status,
    headers: {
      "Content-Type": contentType || "application/json",
    },
  });

  const cart = upstream.headers.get("x-cart-token");
  if (cart) res.headers.set("X-Cart-Token", cart);
  const reqId = upstream.headers.get("x-request-id");
  if (reqId) res.headers.set("X-Request-Id", reqId);
  const retryAfter = upstream.headers.get("retry-after");
  if (retryAfter) res.headers.set("Retry-After", retryAfter);

  if (opts?.clearTokens) clearAuthCookies(res);
  if (opts?.setTokens) {
    setAuthCookies(res, opts.setTokens.access, opts.setTokens.refresh);
  }

  return res;
}

export function applySetCookieFromRefresh(
  res: NextResponse,
  tokens: { access: string; refresh: string } | null,
) {
  if (!tokens) {
    clearAuthCookies(res);
    return;
  }
  setAuthCookies(res, tokens.access, tokens.refresh);
}

export { ACCESS_COOKIE, REFRESH_COOKIE };
