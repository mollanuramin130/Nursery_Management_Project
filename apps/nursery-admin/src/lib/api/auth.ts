import { apiGet, apiSend } from "@/lib/api/client";
import type { AdminUser } from "@/lib/types";

export type LoginResult = {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  user: AdminUser;
};

export async function loginRequest(email: string, password: string) {
  return apiSend<LoginResult>("post", "/auth/login", {
    email,
    password,
    device: { platform: "web" },
  });
}

export async function meRequest() {
  return apiGet<AdminUser>("/auth/me");
}

export async function logoutRequest(refreshToken: string) {
  return apiSend("post", "/auth/logout", { refresh_token: refreshToken });
}
