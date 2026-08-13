import { apiGet, authSend } from "@/lib/api/client";
import type { AdminUser } from "@/lib/types";

export type LoginResult = {
  expires_in?: number;
  user: AdminUser;
};

export async function loginRequest(email: string, password: string) {
  return authSend<LoginResult>("login", {
    email,
    password,
    device: { platform: "web" },
  });
}

export async function meRequest() {
  return apiGet<AdminUser>("/auth/me");
}

export async function logoutRequest() {
  return authSend("logout", {});
}
