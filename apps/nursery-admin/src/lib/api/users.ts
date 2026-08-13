import { apiGet, apiSend } from "@/lib/api/client";

export type AdminManagedUser = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  status: string;
  roles: string[];
  permissions?: string[];
  role_permissions?: Array<{
    slug: string;
    name: string;
    permissions: string[];
  }>;
  last_login_at?: string | null;
  created_at?: string | null;
  total_orders?: number;
  total_spent?: number;
  recent_orders?: Array<{
    id: number;
    order_number: string;
    status: string;
    grand_total: number;
    placed_at?: string | null;
  }>;
};

export type AdminRole = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  permissions: string[];
};

export async function fetchAdminUsers(params?: {
  q?: string;
  role?: string;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminManagedUser[]>("/admin/users", params);
}

export async function fetchAdminUser(id: number) {
  return apiGet<AdminManagedUser>(`/admin/users/${id}`);
}

export async function createAdminUser(payload: {
  name: string;
  email: string;
  phone?: string | null;
  password: string;
  status?: string;
  role_slugs: string[];
}) {
  return apiSend<{ id: number; email: string; roles: string[] }>(
    "post",
    "/admin/users",
    payload,
  );
}

export async function updateAdminUser(
  id: number,
  payload: {
    name?: string;
    email?: string;
    phone?: string | null;
    password?: string;
    status?: string;
    role_slugs?: string[];
  },
) {
  return apiSend<{ id: number; email: string; status: string; roles: string[] }>(
    "put",
    `/admin/users/${id}`,
    payload,
  );
}

export async function deleteAdminUser(id: number) {
  return apiSend<null>("delete", `/admin/users/${id}`);
}

export async function fetchAdminRoles() {
  return apiGet<AdminRole[]>("/admin/roles");
}
