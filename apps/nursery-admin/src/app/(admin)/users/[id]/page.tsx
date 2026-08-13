"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  deleteAdminUser,
  fetchAdminRoles,
  fetchAdminUser,
  updateAdminUser,
  type AdminManagedUser,
  type AdminRole,
} from "@/lib/api/users";
import { hasPermission, permissionDeniedMessage } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "blocked") return "danger" as const;
  return "neutral" as const;
}

export default function UserDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();
  const authUser = useAuthStore((s) => s.user);
  const canManage = hasPermission(authUser, "users.manage");
  const push = useToastStore((s) => s.push);

  const [row, setRow] = useState<AdminManagedUser | null>(null);
  const [rolesCatalog, setRolesCatalog] = useState<AdminRole[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [status, setStatus] = useState("active");
  const [password, setPassword] = useState("");
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError(permissionDeniedMessage("users.manage"));
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [userRes, rolesRes] = await Promise.all([
        fetchAdminUser(id),
        fetchAdminRoles(),
      ]);
      const u = userRes.data;
      setRow(u);
      setRolesCatalog(rolesRes.data);
      setName(u.name);
      setEmail(u.email);
      setPhone(u.phone ?? "");
      setStatus(u.status);
      setSelectedRoles(u.roles ?? []);
      setPassword("");
    } catch (err) {
      const msg =
        err instanceof ApiError && err.status === 403
          ? permissionDeniedMessage("users.manage")
          : err instanceof ApiError || err instanceof Error
            ? err.message
            : "Failed to load user";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [canManage, id]);

  useEffect(() => {
    void load();
  }, [load]);

  const effectivePermissions = useMemo(() => {
    if (row?.permissions?.length) return row.permissions;
    return [];
  }, [row]);

  function toggleRole(slug: string) {
    setSelectedRoles((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug],
    );
  }

  async function onSave(e: FormEvent) {
    e.preventDefault();
    if (!canManage || selectedRoles.length === 0) {
      push("Select at least one role", "error");
      return;
    }
    setSaving(true);
    try {
      await updateAdminUser(id, {
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim() || null,
        status,
        role_slugs: selectedRoles,
        ...(password.trim() ? { password: password.trim() } : {}),
      });
      push("User updated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete() {
    setSaving(true);
    try {
      await deleteAdminUser(id);
      push("User deleted", "success");
      router.push("/users");
    } catch (err) {
      push(err instanceof Error ? err.message : "Delete failed", "error");
      setDeleteOpen(false);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Users & Roles", href: "/users" },
          { label: row?.name ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={row?.name ?? "User"}
        description="Role → effective permissions are read from the API. Passwords are write-only."
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && row ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <FormSection title="User information" description="Profile fields (no secrets shown).">
            <form className="grid gap-3" onSubmit={(e) => void onSave(e)}>
              <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} required />
              <Input
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
              <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
                <option value="blocked">blocked</option>
              </Select>
              <Input
                label="New password (optional)"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Leave blank to keep current"
                autoComplete="new-password"
              />
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={statusTone(row.status)}>Current: {row.status}</Badge>
                <span className="text-xs text-[var(--admin-muted)]">
                  Last login {formatDateTime(row.last_login_at)} · Created{" "}
                  {formatDateTime(row.created_at)}
                </span>
              </div>
              <div className="flex flex-wrap gap-2 pt-2">
                <Button type="submit" disabled={saving || selectedRoles.length === 0}>
                  {saving ? "Saving…" : "Save changes"}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={saving || authUser?.id === id}
                  onClick={() => setDeleteOpen(true)}
                >
                  Delete user
                </Button>
                <Link
                  href="/users"
                  className="inline-flex items-center text-sm text-[var(--admin-primary)] hover:underline"
                >
                  Back to list
                </Link>
              </div>
            </form>
          </FormSection>

          <FormSection
            title="Roles"
            description="Assign one or more roles. Permissions are inherited — not edited independently."
          >
            <div className="grid max-h-80 gap-2 overflow-y-auto">
              {rolesCatalog.map((r) => (
                <label
                  key={r.slug}
                  className="flex cursor-pointer items-start gap-2 rounded border border-[var(--admin-border)] px-3 py-2 text-sm"
                >
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={selectedRoles.includes(r.slug)}
                    onChange={() => toggleRole(r.slug)}
                  />
                  <span>
                    <span className="font-medium">{r.name}</span>
                    <span className="block font-mono text-xs text-[var(--admin-muted)]">
                      {r.slug}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          </FormSection>

          <FormSection
            title="Effective permissions"
            description="Union of permissions from assigned roles (backend source of truth)."
          >
            {effectivePermissions.length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No permissions on this account.</p>
            ) : (
              <ul className="flex flex-wrap gap-1.5">
                {effectivePermissions.map((p) => (
                  <li key={p}>
                    <Badge tone="neutral">{p}</Badge>
                  </li>
                ))}
              </ul>
            )}
            {row.role_permissions?.length ? (
              <div className="mt-4 space-y-3">
                <p className="text-xs font-semibold uppercase text-[var(--admin-muted)]">
                  Role → permissions
                </p>
                {row.role_permissions.map((rp) => (
                  <div key={rp.slug} className="rounded border border-[var(--admin-border)] p-2">
                    <div className="text-sm font-medium">
                      {rp.name}{" "}
                      <span className="font-mono text-xs text-[var(--admin-muted)]">
                        ({rp.slug})
                      </span>
                    </div>
                    <div className="mt-1 text-xs text-[var(--admin-muted)]">
                      {rp.permissions.length
                        ? rp.permissions.join(", ")
                        : "No permissions attached"}
                    </div>
                  </div>
                ))}
              </div>
            ) : null}
          </FormSection>

          <FormSection title="Order activity" description="From user show payload (if any).">
            <p className="text-sm">
              Orders: <strong>{row.total_orders ?? 0}</strong> · Spent:{" "}
              <strong>{formatMoney(row.total_spent ?? 0)}</strong>
            </p>
            {row.recent_orders?.length ? (
              <ul className="mt-2 space-y-1 text-sm">
                {row.recent_orders.slice(0, 8).map((o) => (
                  <li key={o.id}>
                    <Link
                      href={`/orders/${o.id}`}
                      className="text-[var(--admin-primary)] hover:underline"
                    >
                      {o.order_number}
                    </Link>{" "}
                    · {o.status} · {formatMoney(o.grand_total)}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-[var(--admin-muted)]">No recent orders.</p>
            )}
          </FormSection>
        </div>
      ) : null}

      <ConfirmDialog
        open={deleteOpen}
        title="Delete this user?"
        description="This soft-deletes the account. You cannot delete your own login."
        danger
        loading={saving}
        confirmLabel="Delete"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={() => void onDelete()}
      />
    </div>
  );
}
