"use client";

import { create } from "zustand";
import { apiGet, apiSend, authSend, ensureCsrf, setUnauthorizedHandler } from "@/lib/api";
import { storage } from "@/lib/storage";
import type { User } from "@/lib/types";
import { ensureWebDeviceId, registerPushDevice, deactivatePushDevice } from "@/lib/push-register";

type AuthState = {
  user: User | null;
  bootstrapped: boolean;
  loading: boolean;
  bootstrap: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (payload: {
    name: string;
    email: string;
    phone?: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  forgotPassword: (email: string) => Promise<void>;
  resetPassword: (payload: {
    email: string;
    token: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  updateProfile: (payload: { name?: string; phone?: string | null }) => Promise<void>;
  logout: () => Promise<void>;
  clearSession: () => void;
};

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  bootstrapped: false,
  loading: false,

  clearSession: () => {
    storage.clearLegacyAuthTokens();
    set({ user: null });
  },

  bootstrap: async () => {
    setUnauthorizedHandler(() => get().clearSession());
    storage.clearLegacyAuthTokens();
    try {
      await ensureCsrf();
    } catch {
      /* CSRF bootstrap failure — mutations will retry */
    }

    try {
      const res = await apiGet<User>("/auth/me");
      set({ user: res.data, bootstrapped: true });
      void registerPushDevice({
        platform: "web",
        deviceId: ensureWebDeviceId(),
      });
    } catch {
      set({ user: null, bootstrapped: true });
    }
  },

  login: async (email, password) => {
    set({ loading: true });
    try {
      const res = await authSend<{ user: User }>("login", {
        email,
        password,
        device: { platform: "web" },
      });
      // Guest cart merge uses X-Cart-Token on this request; clear local guest cart token after.
      storage.setCartToken(null);
      storage.clearLegacyAuthTokens();
      set({ user: res.data.user });
      void registerPushDevice({
        platform: "web",
        deviceId: ensureWebDeviceId(),
      });
    } finally {
      set({ loading: false });
    }
  },

  register: async (payload) => {
    set({ loading: true });
    try {
      const res = await authSend<{ user: User }>("register", {
        ...payload,
        device: { platform: "web" },
      });
      storage.setCartToken(null);
      storage.clearLegacyAuthTokens();
      set({ user: res.data.user });
    } finally {
      set({ loading: false });
    }
  },

  forgotPassword: async (email) => {
    set({ loading: true });
    try {
      await apiSend("post", "/auth/forgot-password", { email });
    } finally {
      set({ loading: false });
    }
  },

  resetPassword: async (payload) => {
    set({ loading: true });
    try {
      await apiSend("post", "/auth/reset-password", payload);
    } finally {
      set({ loading: false });
    }
  },

  updateProfile: async (payload) => {
    set({ loading: true });
    try {
      const res = await apiSend<User>("put", "/customer/profile", payload);
      set({ user: res.data });
    } finally {
      set({ loading: false });
    }
  },

  logout: async () => {
    try {
      await authSend("logout", {});
    } catch {
      /* ignore network errors — always clear local session */
    }
    await deactivatePushDevice(ensureWebDeviceId());
    get().clearSession();
  },
}));
