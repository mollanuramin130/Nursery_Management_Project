"use client";

import { create } from "zustand";
import { apiGet, apiSend } from "@/lib/api";
import { storage } from "@/lib/storage";
import type { User } from "@/lib/types";

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
  updateProfile: (payload: { name?: string; phone?: string | null }) => Promise<void>;
  logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  bootstrapped: false,
  loading: false,

  bootstrap: async () => {
    const access = storage.getAccess();
    const refresh = storage.getRefresh();
    if (!access && !refresh) {
      set({ user: null, bootstrapped: true });
      return;
    }

    try {
      const res = await apiGet<User>("/auth/me");
      set({ user: res.data, bootstrapped: true });
    } catch {
      storage.clearTokens();
      set({ user: null, bootstrapped: true });
    }
  },

  login: async (email, password) => {
    set({ loading: true });
    try {
      const res = await apiSend<{
        access_token: string;
        refresh_token: string;
        user: User;
      }>("post", "/auth/login", {
        email,
        password,
        device: { platform: "web" },
      });

      storage.setTokens(res.data.access_token, res.data.refresh_token);
      // Guest cart merge uses X-Cart-Token on this request; clear local guest token after.
      storage.setCartToken(null);
      set({ user: res.data.user });
    } finally {
      set({ loading: false });
    }
  },

  register: async (payload) => {
    set({ loading: true });
    try {
      const res = await apiSend<{
        access_token: string;
        refresh_token: string;
        user: User;
      }>("post", "/auth/register", {
        ...payload,
        device: { platform: "web" },
      });

      storage.setTokens(res.data.access_token, res.data.refresh_token);
      storage.setCartToken(null);
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
    const refresh = storage.getRefresh();
    try {
      if (refresh) {
        await apiSend("post", "/auth/logout", { refresh_token: refresh });
      }
    } catch {
      /* ignore network errors — always clear local session */
    }
    storage.clearTokens();
    set({ user: null });
  },
}));
