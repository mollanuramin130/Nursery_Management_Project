"use client";

import Link from "next/link";
import { FormEvent, Suspense, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { sanitizeNext } from "@/lib/auth-redirect";
import { authUserMessage } from "@/lib/auth-messages";
import { isPasswordValid, PASSWORD_HINT, passwordRequirementErrors } from "@/lib/password-rules";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";

function RegisterForm() {
  const register = useAuthStore((s) => s.register);
  const loading = useAuthStore((s) => s.loading);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = useMemo(() => sanitizeNext(searchParams.get("next")), [searchParams]);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  const reqErrors = passwordRequirementErrors(password);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!isPasswordValid(password)) {
      toast(PASSWORD_HINT, "error");
      return;
    }
    if (password !== confirm) {
      toast("Passwords do not match", "error");
      return;
    }
    try {
      await register({
        name,
        email,
        phone: phone || undefined,
        password,
        password_confirmation: confirm,
      });
      await fetchCart();
      toast("Welcome to the nursery");
      router.push(next);
    } catch (err) {
      toast(authUserMessage(err, "Registration failed"), "error");
    }
  }

  const loginHref =
    next && next !== "/account"
      ? `/login?next=${encodeURIComponent(next)}`
      : "/login";

  return (
    <section className="section">
      <div className="container-narrow">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Create account</h1>
          <p>Save wishlist, track orders, and checkout faster next time.</p>
        </div>
        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6 shadow-[var(--shadow-sm)]"
        >
          <Field label="Full name" htmlFor="name">
            <Input
              id="name"
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
          </Field>
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
          <Field label="Phone (optional)" htmlFor="phone">
            <Input
              id="phone"
              type="tel"
              autoComplete="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
            />
          </Field>
          <Field label="Password" htmlFor="password">
            <div className="relative">
              <Input
                id="password"
                type={showPassword ? "text" : "password"}
                autoComplete="new-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
              />
              <button
                type="button"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[var(--color-primary)]"
                onClick={() => setShowPassword((v) => !v)}
              >
                {showPassword ? "Hide" : "Show"}
              </button>
            </div>
            <p className="mt-1 text-xs text-[var(--color-muted)]">{PASSWORD_HINT}</p>
            {password.length > 0 && reqErrors.length > 0 ? (
              <ul className="mt-1 list-inside list-disc text-xs text-red-700">
                {reqErrors.map((err) => (
                  <li key={err}>{err}</li>
                ))}
              </ul>
            ) : null}
          </Field>
          <Field label="Confirm password" htmlFor="confirm">
            <Input
              id="confirm"
              type="password"
              autoComplete="new-password"
              value={confirm}
              onChange={(e) => setConfirm(e.target.value)}
              required
              minLength={8}
            />
          </Field>
          <Button type="submit" fullWidth disabled={loading}>
            {loading ? "Creating account…" : "Create account"}
          </Button>
          <p className="text-sm">
            Already have an account?{" "}
            <Link href={loginHref} className="font-semibold text-[var(--color-primary)]">
              Sign in
            </Link>
          </p>
        </form>
      </div>
    </section>
  );
}

export default function RegisterPage() {
  return (
    <Suspense
      fallback={
        <section className="section">
          <div className="container text-[var(--color-muted)]">Loading…</div>
        </section>
      }
    >
      <RegisterForm />
    </Suspense>
  );
}
