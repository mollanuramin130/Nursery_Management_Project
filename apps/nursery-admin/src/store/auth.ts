"use client";

import { create } from "zustand";
import { loginRequest, logoutRequest, meRequest } from "@/lib/api/auth";
import { setUnauthorizedHandler } from "@/lib/api/client";
import { ensureCsrf } from "@/lib/csrf";
import { isStaffUser } from "@/lib/auth/permissions";
import { storage } from "@/lib/storage";
import type { AdminUser } from "@/lib/types";
import { deactivateAdminPushDevice, registerAdminPushDevice } from "@/lib/push-register";

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
    storage.clearLegacyAuthTokens();
    set({ user: null });
  },

  bootstrap: async () => {
    setUnauthorizedHandler(() => get().clearSession());
    storage.clearLegacyAuthTokens();
    try {
      await ensureCsrf();
    } catch {
      /* mutations retry CSRF */
    }

    try {
      const res = await meRequest();
      if (!isStaffUser(res.data)) {
        await logoutRequest().catch(() => undefined);
        set({ user: null, bootstrapped: true });
        return;
      }
      set({ user: res.data, bootstrapped: true });
      void registerAdminPushDevice();
    } catch {
      set({ user: null, bootstrapped: true });
    }
  },

  login: async (email, password) => {
    set({ loading: true });
    try {
      await loginRequest(email, password);
      storage.clearLegacyAuthTokens();

      const me = await meRequest();
      if (!isStaffUser(me.data)) {
        await logoutRequest().catch(() => undefined);
        throw new Error("This account does not have admin access.");
      }
      set({ user: me.data });
      void registerAdminPushDevice();
    } finally {
      set({ loading: false });
    }
  },

  logout: async () => {
    try {
      await logoutRequest();
    } catch {
      /* always clear local session */
    }
    await deactivateAdminPushDevice();
    storage.clearLegacyAuthTokens();
    set({ user: null });
  },
}));
