import { apiGet, apiSend } from "@/lib/api/client";

export type AdminSetting = {
  key: string;
  value: unknown;
  type: string;
  group: string;
};

export async function fetchSettings(group?: string) {
  return apiGet<AdminSetting[]>("/admin/settings", group ? { group } : undefined);
}

export async function updateSettings(
  settings: Array<{ key: string; value: unknown; type?: string; group?: string }>,
) {
  return apiSend<AdminSetting[]>("put", "/admin/settings", { settings });
}
