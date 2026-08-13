"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { sanitizeNext } from "@/lib/auth-redirect";
import { authUserMessage } from "@/lib/auth-messages";
import { resolveBrowserApiBaseUrl } from "@/lib/api-base";
import { probeApiHealth, type ApiHealthState } from "@/lib/api-health";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";

function LoginForm() {
  const login = useAuthStore((s) => s.login);
  const loading = useAuthStore((s) => s.loading);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = useMemo(() => sanitizeNext(searchParams.get("next")), [searchParams]);

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [apiHealth, setApiHealth] = useState<ApiHealthState>({ status: "checking" });

  const showDemo = process.env.NODE_ENV === "development";

  useEffect(() => {
    void probeApiHealth(resolveBrowserApiBaseUrl()).then(setApiHealth);
  }, []);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (apiHealth.status === "down") {
      toast(apiHealth.detail, "error");
      return;
    }
    try {
      await login(email, password);
      await fetchCart();
      toast("Welcome back");
      router.push(next);
    } catch (err) {
      toast(authUserMessage(err, "Login failed"), "error");
    }
  }

  const registerHref =
    next && next !== "/account"
      ? `/register?next=${encodeURIComponent(next)}`
      : "/register";

  return (
    <section className="section">
      <div className="container-narrow">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Welcome back</h1>
          <p>Sign in to sync cart, wishlist, and orders.</p>
        </div>
        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6 shadow-[var(--shadow-sm)]"
        >
          {apiHealth.status === "checking" ? (
            <p className="rounded-md bg-[var(--color-surface)] px-3 py-2 text-sm text-[var(--color-muted)]">
              Checking API connection…
            </p>
          ) : null}
          {apiHealth.status === "down" ? (
            <p
              className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
              role="alert"
            >
              {apiHealth.detail}
            </p>
          ) : null}
          {apiHealth.status === "ok" && showDemo ? (
            <p className="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
              API ready · demo: asha@example.com / Secret@123
            </p>
          ) : null}
          <Field label="Email" htmlFor="email">
            <Input
              id="email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </Field>
          <Field label="Password" htmlFor="password">
            <div className="relative">
              <Input
                id="password"
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
              <button
                type="button"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[var(--color-primary)]"
                onClick={() => setShowPassword((v) => !v)}
              >
                {showPassword ? "Hide" : "Show"}
              </button>
            </div>
          </Field>
          <div className="flex justify-end">
            <Link
              href="/forgot-password"
              className="text-sm font-semibold text-[var(--color-primary)]"
            >
              Forgot password?
            </Link>
          </div>
          <Button type="submit" fullWidth disabled={loading}>
            {loading ? "Signing in…" : "Sign in"}
          </Button>
          <p className="text-sm">
            New here?{" "}
            <Link href={registerHref} className="font-semibold text-[var(--color-primary)]">
              Create an account
            </Link>
          </p>
          {showDemo ? (
            <p className="text-sm text-[var(--color-muted)]">
              Demo customer (dev): asha@example.com / Secret@123
            </p>
          ) : null}
          <p className="text-sm">
            <Link href="/shop" className="font-semibold text-[var(--color-primary)]">
              Continue shopping
            </Link>
          </p>
        </form>
      </div>
    </section>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <section className="section">
          <div className="container text-[var(--color-muted)]">Loading…</div>
        </section>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
