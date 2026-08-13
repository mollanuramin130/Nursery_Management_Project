"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { createAdminUser, fetchAdminRoles, type AdminRole } from "@/lib/api/users";
import { hasPermission, permissionDeniedMessage } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function NewUserPage() {
  const router = useRouter();
  const authUser = useAuthStore((s) => s.user);
  const canManage = hasPermission(authUser, "users.manage");
  const push = useToastStore((s) => s.push);

  const [roles, setRoles] = useState<AdminRole[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [status, setStatus] = useState("active");
  const [selectedRoles, setSelectedRoles] = useState<string[]>(["order_manager"]);

  useEffect(() => {
    if (!canManage) {
      setLoading(false);
      setError(permissionDeniedMessage("users.manage"));
      return;
    }
    void (async () => {
      setLoading(true);
      try {
        const res = await fetchAdminRoles();
        setRoles(res.data);
        setError(null);
      } catch (err) {
        setError(
          err instanceof ApiError || err instanceof Error
            ? err.message
            : "Failed to load roles",
        );
      } finally {
        setLoading(false);
      }
    })();
  }, [canManage]);

  function toggleRole(slug: string) {
    setSelectedRoles((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug],
    );
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!canManage) return;
    if (selectedRoles.length === 0) {
      push("Select at least one role", "error");
      return;
    }
    setSaving(true);
    try {
      const res = await createAdminUser({
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim() || null,
        password,
        status,
        role_slugs: selectedRoles,
      });
      push("User created", "success");
      router.push(`/users/${res.data.id}`);
    } catch (err) {
      push(err instanceof Error ? err.message : "Create failed", "error");
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
          { label: "Create" },
        ]}
      />
      <PageHeader title="Create user" description="Creates via POST /admin/users. Password is never shown again." />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} /> : null}

      {!loading && !error && canManage ? (
        <FormSection title="New account">
          <form className="grid max-w-xl gap-3" onSubmit={(e) => void onSubmit(e)}>
            <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} required />
            <Input
              label="Email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
            <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
            <Input
              label="Password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
            />
            <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="active">active</option>
              <option value="inactive">inactive</option>
              <option value="blocked">blocked</option>
            </Select>

            <div>
              <p className="mb-2 text-sm font-medium">Roles</p>
              <div className="grid max-h-64 gap-2 overflow-y-auto">
                {roles.map((r) => (
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
                        {r.slug} · {r.permissions.length} permissions
                      </span>
                    </span>
                  </label>
                ))}
              </div>
            </div>

            <div className="flex gap-2">
              <Button type="submit" disabled={saving || selectedRoles.length === 0}>
                {saving ? "Creating…" : "Create user"}
              </Button>
              <Link href="/users">
                <Button type="button" variant="secondary">
                  Cancel
                </Button>
              </Link>
            </div>
          </form>
        </FormSection>
      ) : null}
    </div>
  );
}
