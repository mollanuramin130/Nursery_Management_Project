"use client";

import { create } from "zustand";
import { loginRequest, logoutRequest, meRequest } from "@/lib/api/auth";
import { setUnauthorizedHandler } from "@/lib/api/client";
import { isStaffUser } from "@/lib/auth/permissions";
import { storage } from "@/lib/storage";
import type { AdminUser } from "@/lib/types";

type AuthState = {
  user: AdminUser | null;
  bootstrapped: boolean;
  loading: boolean;
  bootstrap: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  clearSession: () => void;
};

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  bootstrapped: false,
  loading: false,

  clearSession: () => {
    storage.clearTokens();
    set({ user: null });
  },

  bootstrap: async () => {
    setUnauthorizedHandler(() => get().clearSession());
    const access = storage.getAccess();
    const refresh = storage.getRefresh();
    if (!access && !refresh) {
      set({ user: null, bootstrapped: true });
      return;
    }

    try {
      const res = await meRequest();
      if (!isStaffUser(res.data)) {
        storage.clearTokens();
        set({ user: null, bootstrapped: true });
        return;
      }
      set({ user: res.data, bootstrapped: true });
    } catch {
      storage.clearTokens();
      set({ user: null, bootstrapped: true });
    }
  },

  login: async (email, password) => {
    set({ loading: true });
    try {
      const res = await loginRequest(email, password);
      storage.setTokens(res.data.access_token, res.data.refresh_token);

      const me = await meRequest();
      if (!isStaffUser(me.data)) {
        storage.clearTokens();
        throw new Error("This account does not have admin access.");
      }
      set({ user: me.data });
    } finally {
      set({ loading: false });
    }
  },

  logout: async () => {
    const refresh = storage.getRefresh();
    try {
      if (refresh) await logoutRequest(refresh);
    } catch {
      /* always clear local session */
    }
    storage.clearTokens();
    set({ user: null });
  },
}));
