import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";
import { ensureCsrf, getCsrfToken } from "@/lib/csrf";
import { storage } from "@/lib/storage";
import type { ApiEnvelope } from "@/lib/types";

const proxyBase = "/api/bff/proxy";
const authBase = "/api/bff/auth";

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
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-Token": getCsrfToken() ?? "",
        },
      },
    );
    return !!data.success;
  } catch (error) {
    // QA-37: transport failures must not clear staff session.
    const ax = error as { response?: { status?: number }; code?: string };
    if (!ax.response) {
      return false;
    }
    if (ax.response.status === 401 || ax.response.status === 403) {
      storage.clearLegacyAuthTokens();
      onUnauthorized?.();
    }
    return false;
  }
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? "get").toUpperCase();
  if (method !== "GET" && method !== "HEAD") {
    const csrf = getCsrfToken() ?? (await ensureCsrf());
    config.headers["X-CSRF-Token"] = csrf;
  }
  if (!config.headers["X-Request-Id"]) {
    const id =
      typeof crypto !== "undefined" && "randomUUID" in crypto
        ? `admin_${crypto.randomUUID()}`
        : `admin_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
    config.headers["X-Request-Id"] = id;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<ApiEnvelope<unknown>>) => {
    const original = error.config as RetriableConfig | undefined;
    if (!original || error.response?.status !== 401 || original._retry) {
      return Promise.reject(error);
    }

    const url = String(original.url ?? "");
    if (
      url.includes("/auth/login") ||
      url.includes("/auth/refresh") ||
      url.includes("/auth/logout")
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

export class ApiError extends Error {
  status?: number;
  code?: string;
  errors?: Record<string, string[]> | null;
  requestId?: string;

  constructor(
    message: string,
    opts?: {
      status?: number;
      code?: string;
      errors?: Record<string, string[]> | null;
      requestId?: string;
    },
  ) {
    super(message);
    this.name = "ApiError";
    this.status = opts?.status;
    this.code = opts?.code;
    this.errors = opts?.errors;
    this.requestId = opts?.requestId;
  }
}

function unwrapError(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as ApiEnvelope<unknown> | undefined;
    const requestId =
      (typeof error.response?.headers?.["x-request-id"] === "string"
        ? error.response.headers["x-request-id"]
        : undefined) ??
      (typeof body?.meta?.request_id === "string" ? body.meta.request_id : undefined);
    if (error.response?.status === 429) {
      const apiMsg = body?.message?.trim() ?? "";
      const message =
        apiMsg && !/^too many attempts\.?$/i.test(apiMsg)
          ? apiMsg
          : "You're doing that too quickly. Please wait about a minute, then try again.";
      return new ApiError(message, {
        status: 429,
        errors: body?.errors ?? null,
        requestId,
      });
    }
    if (body?.message) {
      return new ApiError(body.message, {
        status: error.response?.status,
        code:
          typeof body.meta?.error_code === "string"
            ? body.meta.error_code
            : undefined,
        errors: body.errors ?? null,
        requestId,
      });
    }
    if (!error.response) {
      return new ApiError(
        "Cannot reach Admin BFF / API. Confirm nursery-admin is running and API_PROXY_TARGET points at Laravel.",
      );
    }
    return new ApiError(fallback, {
      status: error.response?.status,
      errors: body?.errors ?? null,
      requestId,
    });
  }
  if (error instanceof Error) return new ApiError(error.message);
  return new ApiError(fallback);
}

export async function apiGet<T>(url: string, params?: Record<string, unknown>) {
  try {
    const { data } = await api.get<ApiEnvelope<T>>(url, { params });
    if (!data.success) throw new ApiError(data.message || "Request failed");
    return data;
  } catch (error) {
    throw unwrapError(error, "Request failed");
  }
}

export async function apiSend<T>(
  method: "post" | "put" | "delete",
  url: string,
  body?: unknown,
) {
  try {
    const { data } = await api.request<ApiEnvelope<T>>({
      method,
      url,
      data: body,
    });
    if (!data.success) throw new ApiError(data.message || "Request failed");
    return data;
  } catch (error) {
    throw unwrapError(error, "Request failed");
  }
}

export async function authSend<T>(
  path: "login" | "logout" | "refresh",
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
        "X-Platform": "web",
      },
    });
    if (!data.success) throw new ApiError(data.message || "Request failed");
    return data;
  } catch (error) {
    throw unwrapError(error, "Request failed");
  }
}
