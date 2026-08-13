/**
 * QA-22 — Admin Web push registration (mirrors customer; separate device key).
 */
import { apiSend } from "@/lib/api/client";

const DEVICE_KEY = "gl_admin_web_device_id";

export function ensureAdminWebDeviceId(): string {
  if (typeof window === "undefined") return "ssr";
  let id = localStorage.getItem(DEVICE_KEY);
  if (!id) {
    id =
      typeof crypto !== "undefined" && "randomUUID" in crypto
        ? crypto.randomUUID()
        : `admin-web-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    localStorage.setItem(DEVICE_KEY, id);
  }
  return id;
}

export async function registerAdminPushDevice(): Promise<void> {
  try {
    await apiSend("post", "/devices/register", {
      platform: "admin_web",
      device_id: ensureAdminWebDeviceId(),
      app_version: "admin-web",
      push_token: null,
    });
  } catch {
    /* inbox works without push */
  }
}

export async function deactivateAdminPushDevice(): Promise<void> {
  try {
    await apiSend("post", "/devices/deactivate", {
      device_id: ensureAdminWebDeviceId(),
    });
  } catch {
    /* ignore */
  }
}
