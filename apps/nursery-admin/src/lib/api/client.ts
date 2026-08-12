import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";
import { storage } from "@/lib/storage";
import type { ApiEnvelope } from "@/lib/types";

const baseURL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

export const api = axios.create({
  baseURL,
  timeout: 30000,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Platform": "web",
    "X-App-Version": "1.0.0",
  },
});

type RetriableConfig = InternalAxiosRequestConfig & { _retry?: boolean };

let refreshPromise: Promise<string | null> | null = null;
let onUnauthorized: (() => void) | null = null;

export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler;
}

async function refreshAccessToken(): Promise<string | null> {
  const refresh = storage.getRefresh();
  if (!refresh) return null;

  try {
    const { data } = await axios.post<
      ApiEnvelope<{ access_token: string; refresh_token: string }>
    >(
      `${baseURL}/auth/refresh`,
      { refresh_token: refresh },
      {
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
      },
    );

    if (!data.success) return null;
    storage.setTokens(data.data.access_token, data.data.refresh_token);
    return data.data.access_token;
  } catch {
    storage.clearTokens();
    return null;
  }
}

api.interceptors.request.use((config) => {
  const access = storage.getAccess();
  if (access) {
    config.headers.Authorization = `Bearer ${access}`;
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

    if (
      original.url?.includes("/auth/login") ||
      original.url?.includes("/auth/refresh") ||
      original.url?.includes("/auth/logout")
    ) {
      return Promise.reject(error);
    }

    original._retry = true;
    refreshPromise ??= refreshAccessToken().finally(() => {
      refreshPromise = null;
    });
    const token = await refreshPromise;
    if (!token) {
      onUnauthorized?.();
      return Promise.reject(error);
    }
    original.headers.Authorization = `Bearer ${token}`;
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
    if (body?.message) {
      return new ApiError(body.message, {
        status: error.response?.status,
        errors: body.errors ?? null,
        requestId,
      });
    }
    if (!error.response) {
      return new ApiError(
        `Cannot reach API at ${baseURL}. Check the API is running and CORS allows this Admin origin (port 3001).`,
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
