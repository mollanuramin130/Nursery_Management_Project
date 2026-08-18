"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { ToastViewport } from "@/components/feedback/ToastViewport";
import { authUserMessage } from "@/lib/auth-messages";
import { sanitizeAdminNext } from "@/lib/auth-redirect";
import { probeApiHealth, type ApiHealthState } from "@/lib/api-health";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function LoginClient() {
  const router = useRouter();
  const params = useSearchParams();
  const { user, bootstrapped, loading, login } = useAuthStore();
  const push = useToastStore((s) => s.push);
  const [email, setEmail] = useState(
    process.env.NODE_ENV === "development" ? "admin@nursery.test" : "",
  );
  const [password, setPassword] = useState(
    process.env.NODE_ENV === "development" ? "Secret@123" : "",
  );
  const [error, setError] = useState<string | null>(null);
  const [apiHealth, setApiHealth] = useState<ApiHealthState>({ status: "checking" });
  const safeNext = sanitizeAdminNext(params.get("next"));

  useEffect(() => {
    if (bootstrapped && user) {
      router.replace(safeNext);
    }
  }, [bootstrapped, user, router, safeNext]);

  useEffect(() => {
    const base =
      "/api/bff/proxy";
    void probeApiHealth(base).then(setApiHealth);
  }, []);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (loading || apiHealth.status === "down") {
      if (apiHealth.status === "down") setError(apiHealth.detail);
      return;
    }
    try {
      const ok = await login(email.trim(), password);
      if (!ok) return;
      push("Signed in", "success");
      router.replace(safeNext);
    } catch (err) {
      setError(authUserMessage(err, "Login failed"));
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-[linear-gradient(160deg,#123024_0%,#1a5c3a_42%,#f4f6f5_42%)] px-4">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-6 shadow-[var(--admin-shadow)]"
      >
        <div className="mb-6">
          <div className="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--admin-primary)]">
            GreenLeaf Nursery
          </div>
          <h1 className="mt-1 text-2xl font-semibold text-[var(--admin-ink)]">Admin Portal</h1>
          <p className="mt-1 text-sm text-[var(--admin-muted)]">
            Sign in with a staff account to manage operations.
          </p>
          {apiHealth.status === "checking" ? (
            <p className="mt-3 text-xs text-[var(--admin-muted)]">Checking API connection…</p>
          ) : null}
          {apiHealth.status === "down" ? (
            <p className="mt-3 rounded-[var(--admin-radius)] bg-[var(--admin-danger-soft)] px-3 py-2 text-sm text-[var(--admin-danger)]" role="alert">
              {apiHealth.detail}
            </p>
          ) : null}
          {apiHealth.status === "ok" ? (
            <p className="mt-3 text-xs text-[var(--admin-success,#027a48)]">API ready</p>
          ) : null}
        </div>

        <div className="space-y-3">
          <Input
            label="Email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
          <Input
            label="Password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          {error ? (
            <div className="rounded-[var(--admin-radius)] bg-[var(--admin-danger-soft)] px-3 py-2 text-sm text-[var(--admin-danger)]">
              {error}
            </div>
          ) : null}
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? "Signing in…" : "Sign in"}
          </Button>
        </div>
      </form>
      <ToastViewport />
    </div>
  );
}
