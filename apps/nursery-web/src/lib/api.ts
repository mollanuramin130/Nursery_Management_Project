import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";
import {
  AuthApiError,
  authRateLimitMessage,
  formatAuthCountdown,
  parseRetryAfterSeconds,
} from "@/lib/auth-messages";
import { ensureCsrf, getCsrfToken, setCsrfToken } from "@/lib/csrf";
import {
  classifyHttpStatus,
  classifyTransport,
  isTransientAxiosFailure,
} from "@/lib/network-errors";
import { storage } from "@/lib/storage";
import type { ApiEnvelope } from "@/lib/types";
import { useNetworkStatusStore } from "@/store/network-status";

const DEFAULT_AUTH_RETRY_AFTER_SECONDS = 60;

function retryAfterFromAxios(error: AxiosError): number {
  const headers = error.response?.headers;
  const raw =
    headers?.["retry-after"] ??
    headers?.["Retry-After"] ??
    (typeof headers?.get === "function" ? headers.get("retry-after") : null);
  return (
    parseRetryAfterSeconds(raw as string | number | null | undefined) ??
    DEFAULT_AUTH_RETRY_AFTER_SECONDS
  );
}

function throwApiFailure(error: unknown, fallback: string): never {
  if (axios.isAxiosError(error) && error.response?.status === 429) {
    const retryAfterSeconds = retryAfterFromAxios(error);
    const path = String(error.config?.url ?? "");
    const msg =
      path.includes("/orders") && !path.includes("/orders/")
        ? `You tried to place an order too many times. Please wait ${formatAuthCountdown(retryAfterSeconds)}, then try again.`
        : authRateLimitMessage(retryAfterSeconds);
    useNetworkStatusStore.getState().reportFailure({
      kind: "rateLimited",
      userMessage: msg,
      statusCode: 429,
      retryable: true,
    });
    throw new AuthApiError(msg, { statusCode: 429, retryAfterSeconds });
  }
  throw new Error(unwrapError(error, fallback));
}

/** QA-33: browser talks only to same-origin BFF — JWTs stay HttpOnly. */
const proxyBase = "/api/bff/proxy";
const authBase = "/api/bff/auth";

if (
  process.env.NODE_ENV === "production" &&
  process.env.NEXT_PUBLIC_API_BASE_URL &&
  /localhost|127\.0\.0\.1/i.test(process.env.NEXT_PUBLIC_API_BASE_URL)
) {
  console.error(
    "CRITICAL: NEXT_PUBLIC_API_BASE_URL must be an HTTPS production API URL (not localhost).",
  );
}

export const api = axios.create({
  baseURL: proxyBase,
  timeout: 30000,
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Platform": "web",
    "X-App-Version": "1.0.0",
  },
});

type RetriableConfig = InternalAxiosRequestConfig & { _retry?: boolean };

let refreshPromise: Promise<boolean> | null = null;
let onUnauthorized: (() => void) | null = null;

/** Clear in-memory auth when refresh fails (QA-11 / QA-33). */
export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler;
}

async function refreshSession(): Promise<boolean> {
  try {
    await ensureCsrf();
    const { data } = await axios.post<ApiEnvelope<{ expires_in?: number }>>(
      `${authBase}/refresh`,
      {},
      {
        withCredentials: true,
        timeout: 15000,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-Token": getCsrfToken() ?? "",
        },
      },
    );
    return !!data.success;
  } catch (error) {
    // QA-37: transport failures must NOT clear the HttpOnly session.
    if (axios.isAxiosError(error) && isTransientAxiosFailure(error)) {
      useNetworkStatusStore.getState().reportFailure(
        classifyTransport({
          timedOut: error.code === "ECONNABORTED",
          browserOffline: typeof navigator !== "undefined" && !navigator.onLine,
        }),
      );
      return false;
    }
    if (axios.isAxiosError(error) && (error.response?.status === 401 || error.response?.status === 403)) {
      storage.clearTokens();
      onUnauthorized?.();
      return false;
    }
    // Ambiguous refresh failure — keep cookies; surface reconnect.
    useNetworkStatusStore.getState().reportFailure(
      classifyTransport({
        browserOffline: typeof navigator !== "undefined" && !navigator.onLine,
      }),
    );
    return false;
  }
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? "get").toUpperCase();
  if (method !== "GET" && method !== "HEAD") {
    const csrf = getCsrfToken() ?? (await ensureCsrf());
    config.headers["X-CSRF-Token"] = csrf;
  }
  const cart = storage.getCartToken();
  if (cart) {
    config.headers["X-Cart-Token"] = cart;
  }
  const guest = storage.getGuestToken();
  if (guest) {
    config.headers["X-Guest-Token"] = guest;
  }
  if (!config.headers["X-Request-Id"]) {
    const id =
      typeof crypto !== "undefined" && "randomUUID" in crypto
        ? `web_${crypto.randomUUID()}`
        : `web_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
    config.headers["X-Request-Id"] = id;
  }
  return config;
});

api.interceptors.response.use(
  (response) => {
    const cartToken = response.headers["x-cart-token"];
    if (typeof cartToken === "string" && cartToken) {
      storage.setCartToken(cartToken);
    }
    const body = response.data as ApiEnvelope<{ cart_token?: string | null }>;
    if (body?.data && typeof body.data === "object" && "cart_token" in body.data) {
      const token = body.data.cart_token;
      if (token) storage.setCartToken(token);
    }
    return response;
  },
  async (error: AxiosError<ApiEnvelope<unknown>>) => {
    const original = error.config as RetriableConfig | undefined;
    if (!original || error.response?.status !== 401 || original._retry) {
      return Promise.reject(error);
    }

    const url = String(original.url ?? "");
    if (
      url.includes("/auth/login") ||
      url.includes("/auth/register") ||
      url.includes("/auth/refresh") ||
      url.includes("/auth/forgot-password") ||
      url.includes("/auth/reset-password")
    ) {
      return Promise.reject(error);
    }

    original._retry = true;
    refreshPromise ??= refreshSession().finally(() => {
      refreshPromise = null;
    });
    const ok = await refreshPromise;
    if (!ok) {
      onUnauthorized?.();
      return Promise.reject(error);
    }
    return api(original);
  },
);

function unwrapError(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    if (!error.response) {
      const classified = classifyTransport({
        timedOut: error.code === "ECONNABORTED",
        browserOffline: typeof navigator !== "undefined" && !navigator.onLine,
      });
      useNetworkStatusStore.getState().reportFailure(classified);
      return classified.userMessage;
    }
    const apiMsg = (error.response.data as ApiEnvelope<unknown> | undefined)?.message;
    if (error.response.status === 429) {
      const path = String(error.config?.url ?? "");
      if (path.includes("/orders") && !path.includes("/orders/")) {
        const msg =
          "You tried to place an order too many times. Please wait about a minute, then try again.";
        useNetworkStatusStore.getState().reportFailure({
          kind: "rateLimited",
          userMessage: msg,
          statusCode: 429,
          retryable: true,
        });
        return msg;
      }
    }
    const classified = classifyHttpStatus(error.response.status, apiMsg);
    useNetworkStatusStore.getState().reportFailure(classified);
    return classified.userMessage;
  }
  if (error instanceof Error && error.message) return error.message;
  return fallback;
}

export async function apiGet<T>(url: string, params?: Record<string, unknown>) {
  try {
    const { data } = await api.get<ApiEnvelope<T>>(url, { params });
    if (!data.success) throw new Error(data.message || "Request failed");
    useNetworkStatusStore.getState().reportSuccess();
    return data;
  } catch (error) {
    throwApiFailure(error, "Request failed");
  }
}

export async function apiSend<T>(
  method: "post" | "put" | "delete",
  url: string,
  body?: unknown,
  headers?: Record<string, string>,
) {
  try {
    const { data } = await api.request<ApiEnvelope<T>>({
      method,
      url,
      data: body,
      headers,
    });
    if (!data.success) throw new Error(data.message || "Request failed");
    useNetworkStatusStore.getState().reportSuccess();
    return data;
  } catch (error) {
    throwApiFailure(error, "Request failed");
  }
}

/** Auth endpoints that set HttpOnly cookies (tokens never returned to JS). */
export async function authSend<T>(
  path: "login" | "register" | "logout" | "refresh",
  body?: unknown,
) {
  await ensureCsrf();
  try {
    const { data } = await axios.request<ApiEnvelope<T>>({
      method: "post",
      url: `${authBase}/${path}`,
      data: body ?? {},
      withCredentials: true,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": getCsrfToken() ?? "",
        "X-Cart-Token": storage.getCartToken() ?? "",
        "X-Guest-Token": storage.getGuestToken(),
        "X-Platform": "web",
      },
    });
    if (!data.success) throw new Error(data.message || "Request failed");
    return data;
  } catch (error) {
    throwApiFailure(error, "Request failed");
  }
}

export { setCsrfToken, ensureCsrf };
