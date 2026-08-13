/**
 * QA-22 — optional browser FCM registration.
 * LIVE push requires NEXT_PUBLIC_FIREBASE_* + FCM_SERVER_KEY on API.
 * When config is missing, registration is a no-op (in-app inbox still works).
 */

import { apiSend } from "@/lib/api";

export type PushRegisterOptions = {
  platform: "web" | "admin_web";
  deviceId: string;
  appVersion?: string;
};

const DEVICE_KEY = "gl_web_device_id";

export function ensureWebDeviceId(): string {
  if (typeof window === "undefined") return "ssr";
  let id = localStorage.getItem(DEVICE_KEY);
  if (!id) {
    id = typeof crypto !== "undefined" && "randomUUID" in crypto
      ? crypto.randomUUID()
      : `web-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    localStorage.setItem(DEVICE_KEY, id);
  }
  return id;
}

function firebaseConfigured(): boolean {
  return Boolean(
    process.env.NEXT_PUBLIC_FIREBASE_API_KEY &&
      process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_APP_ID &&
      process.env.NEXT_PUBLIC_FIREBASE_VAPID_KEY,
  );
}

export function isBrowserPushSupported(): boolean {
  return typeof window !== "undefined" && "Notification" in window && "serviceWorker" in navigator;
}

/** Register device with optional FCM token (never throws). */
export async function registerPushDevice(opts: PushRegisterOptions): Promise<{ registered: boolean; mode: string }> {
  try {
    let pushToken: string | undefined;
    if (firebaseConfigured() && isBrowserPushSupported()) {
      // Dynamic import keeps the app working when Firebase SDK is not installed yet.
      try {
        const mod = await import("./firebase-messaging-client");
        pushToken = (await mod.getWebFcmToken()) ?? undefined;
      } catch {
        pushToken = undefined;
      }
    }

    await apiSend("post", "/devices/register", {
      platform: opts.platform,
      device_id: opts.deviceId,
      push_token: pushToken ?? null,
      app_version: opts.appVersion ?? "web",
    });

    return { registered: true, mode: pushToken ? "fcm" : "device_only" };
  } catch {
    return { registered: false, mode: "failed" };
  }
}

export async function deactivatePushDevice(deviceId: string): Promise<void> {
  try {
    await apiSend("post", "/devices/deactivate", { device_id: deviceId });
  } catch {
    /* ignore */
  }
}
