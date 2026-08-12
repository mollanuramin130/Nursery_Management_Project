import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";
import { storage } from "@/lib/storage";
import type { ApiEnvelope } from "@/lib/types";

const baseURL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

if (
  process.env.NODE_ENV === "production" &&
  /localhost|127\.0\.0\.1/i.test(baseURL)
) {
  console.error(
    "CRITICAL: NEXT_PUBLIC_API_BASE_URL must be an HTTPS production API URL (not localhost).",
  );
}

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

async function refreshAccessToken(): Promise<string | null> {
  const refresh = storage.getRefresh();
  if (!refresh) return null;

  try {
    const { data } = await axios.post<
      ApiEnvelope<{ access_token: string; refresh_token: string }>
    >(`${baseURL}/auth/refresh`, { refresh_token: refresh }, {
      headers: { Accept: "application/json", "Content-Type": "application/json" },
    });

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

    if (
      original.url?.includes("/auth/login") ||
      original.url?.includes("/auth/register") ||
      original.url?.includes("/auth/refresh") ||
      original.url?.includes("/auth/forgot-password") ||
      original.url?.includes("/auth/reset-password")
    ) {
      return Promise.reject(error);
    }

    original._retry = true;
    refreshPromise ??= refreshAccessToken().finally(() => {
      refreshPromise = null;
    });
    const token = await refreshPromise;
    if (!token) return Promise.reject(error);
    original.headers.Authorization = `Bearer ${token}`;
    return api(original);
  },
);

function unwrapError(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    const msg = (error.response?.data as ApiEnvelope<unknown> | undefined)?.message;
    if (msg) return msg;
  }
  if (error instanceof Error && error.message) return error.message;
  return fallback;
}

export async function apiGet<T>(url: string, params?: Record<string, unknown>) {
  try {
    const { data } = await api.get<ApiEnvelope<T>>(url, { params });
    if (!data.success) throw new Error(data.message || "Request failed");
    return data;
  } catch (error) {
    throw new Error(unwrapError(error, "Request failed"));
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
    return data;
  } catch (error) {
    throw new Error(unwrapError(error, "Request failed"));
  }
}
