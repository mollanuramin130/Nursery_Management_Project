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
  login: (identifier: string, password: string) => Promise<boolean>;
  loginOtp: (mobile: string, code: string) => Promise<boolean>;
  sendOtp: (mobile: string, purpose: "register" | "login" | "reset") => Promise<{ otp_verified_token?: string; verification_id?: number; expires_in?: number; mobile?: string }>;
  verifyOtp: (mobile: string, code: string, purpose: "register" | "login" | "reset") => Promise<{ otp_verified_token: string }>;
  register: (payload: {
    name: string;
    mobile: string;
    email?: string;
    otp_verified_token: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  forgotPassword: (email: string) => Promise<void>;
  resetPasswordViaMobile: (mobile: string, otpVerifiedToken: string, password: string, passwordConfirmation: string) => Promise<void>;
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

let loginInFlight = false;
let registerInFlight = false;
let passwordInFlight = false;

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

  login: async (identifier, password) => {
    if (loginInFlight || get().loading) {
      if (process.env.NODE_ENV === "development") {
        console.debug("[AUTH] duplicate login prevented");
      }
      return false;
    }
    loginInFlight = true;
    set({ loading: true });
    try {
      const isMobile = /^\+?\d{10,15}$/.test(identifier.replace(/\s/g, ""));
      const body = isMobile
        ? { mobile: identifier, password, device: { platform: "web" as const } }
        : { email: identifier, password, device: { platform: "web" as const } };
      const res = await authSend<{ user: User }>("login", body);
      storage.setCartToken(null);
      storage.clearLegacyAuthTokens();
      set({ user: res.data.user });
      void registerPushDevice({ platform: "web", deviceId: ensureWebDeviceId() });
      return true;
    } finally {
      loginInFlight = false;
      set({ loading: false });
    }
  },

  loginOtp: async (mobile, code) => {
    if (loginInFlight || get().loading) return false;
    loginInFlight = true;
    set({ loading: true });
    try {
      const res = await authSend<{ user: User }>("login-otp", {
        mobile,
        code,
        device: { platform: "web" },
      });
      storage.setCartToken(null);
      storage.clearLegacyAuthTokens();
      set({ user: res.data.user });
      void registerPushDevice({ platform: "web", deviceId: ensureWebDeviceId() });
      return true;
    } finally {
      loginInFlight = false;
      set({ loading: false });
    }
  },

  sendOtp: async (mobile, purpose) => {
    const res = await authSend<{ otp_verified_token?: string; verification_id?: number; expires_in?: number; mobile?: string }>("otp/send", { mobile, purpose });
    return res.data;
  },

  verifyOtp: async (mobile, code, purpose) => {
    const res = await authSend<{ otp_verified_token: string }>("otp/verify", { mobile, code, purpose });
    return res.data;
  },

  register: async (payload) => {
    if (registerInFlight || get().loading) {
      if (process.env.NODE_ENV === "development") {
        console.debug("[AUTH] duplicate register prevented");
      }
      return;
    }
    registerInFlight = true;
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
      registerInFlight = false;
      set({ loading: false });
    }
  },

  forgotPassword: async (email) => {
    if (passwordInFlight || get().loading) return;
    passwordInFlight = true;
    set({ loading: true });
    try {
      await apiSend("post", "/auth/forgot-password", { email });
    } finally {
      passwordInFlight = false;
      set({ loading: false });
    }
  },

  resetPasswordViaMobile: async (mobile, otpVerifiedToken, password, passwordConfirmation) => {
    if (passwordInFlight || get().loading) return;
    passwordInFlight = true;
    set({ loading: true });
    try {
      await authSend("reset-password-mobile", {
        mobile,
        otp_verified_token: otpVerifiedToken,
        password,
        password_confirmation: passwordConfirmation,
      });
    } finally {
      passwordInFlight = false;
      set({ loading: false });
    }
  },

  resetPassword: async (payload) => {
    if (passwordInFlight || get().loading) return;
    passwordInFlight = true;
    set({ loading: true });
    try {
      await apiSend("post", "/auth/reset-password", payload);
    } finally {
      passwordInFlight = false;
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
