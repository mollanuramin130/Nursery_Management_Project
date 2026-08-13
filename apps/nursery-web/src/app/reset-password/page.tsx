"use client";

import Link from "next/link";
import { FormEvent, Suspense, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { isPasswordValid, PASSWORD_HINT, passwordRequirementErrors } from "@/lib/password-rules";
import { authUserMessage } from "@/lib/auth-messages";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function ResetPasswordForm() {
  const resetPassword = useAuthStore((s) => s.resetPassword);
  const loading = useAuthStore((s) => s.loading);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const searchParams = useSearchParams();

  const initialEmail = useMemo(() => searchParams.get("email") ?? "", [searchParams]);
  const initialToken = useMemo(() => searchParams.get("token") ?? "", [searchParams]);

  const [email, setEmail] = useState(initialEmail);
  const [token, setToken] = useState(initialToken);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [done, setDone] = useState(false);

  const reqErrors = passwordRequirementErrors(password);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!token.trim()) {
      toast("Reset token is required. Open the link from your email.", "error");
      return;
    }
    if (!isPasswordValid(password)) {
      toast(PASSWORD_HINT, "error");
      return;
    }
    if (password !== confirm) {
      toast("Passwords do not match", "error");
      return;
    }
    try {
      await resetPassword({
        email: email.trim(),
        token: token.trim(),
        password,
        password_confirmation: confirm,
      });
      setDone(true);
      toast("Password updated. You can sign in.");
      router.push("/login");
    } catch (err) {
      toast(authUserMessage(err, "Reset failed"), "error");
    }
  }

  return (
    <section className="section">
      <div className="container-narrow">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Reset password</h1>
          <p>Choose a new password for your GreenLeaf account.</p>
        </div>
        {done ? (
          <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6">
            <p className="font-semibold text-[var(--color-primary-deep)]">Password updated</p>
            <Link href="/login" className="mt-4 inline-flex text-sm font-semibold text-[var(--color-primary)]">
              Sign in
            </Link>
          </div>
        ) : (
          <form
            onSubmit={onSubmit}
            className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6 shadow-[var(--shadow-sm)]"
          >
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
            {!initialToken ? (
              <Field label="Reset token" htmlFor="token">
                <Input
                  id="token"
                  value={token}
                  onChange={(e) => setToken(e.target.value)}
                  required
                  placeholder="Paste token from email if link did not prefill"
                />
              </Field>
            ) : (
              <input type="hidden" value={token} readOnly />
            )}
            <Field label="New password" htmlFor="password">
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
              {loading ? "Updating…" : "Update password"}
            </Button>
            <p className="text-sm">
              <Link href="/login" className="font-semibold text-[var(--color-primary)]">
                Back to sign in
              </Link>
            </p>
          </form>
        )}
      </div>
    </section>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense
      fallback={
        <section className="section">
          <div className="container text-[var(--color-muted)]">Loading…</div>
        </section>
      }
    >
      <ResetPasswordForm />
    </Suspense>
  );
}
