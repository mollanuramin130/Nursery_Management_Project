"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { authUserMessage } from "@/lib/auth-messages";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function ForgotPasswordPage() {
  const forgotPassword = useAuthStore((s) => s.forgotPassword);
  const loading = useAuthStore((s) => s.loading);
  const toast = useToastStore((s) => s.push);
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    try {
      await forgotPassword(email.trim());
      setSent(true);
      toast("If that email exists, a reset link was sent.");
    } catch (err) {
      toast(authUserMessage(err, "Request failed"), "error");
    }
  }

  return (
    <section className="section">
      <div className="container-narrow">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Forgot password</h1>
          <p>We’ll email reset instructions if the account exists.</p>
        </div>
        {sent ? (
          <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6">
            <p className="font-semibold text-[var(--color-primary-deep)]">Check your inbox</p>
            <p className="mt-2 text-sm text-[var(--color-muted)]">
              If an account exists for that email, you’ll receive a password reset link shortly.
              Open the link (or use{" "}
              <Link href="/reset-password" className="font-semibold text-[var(--color-primary)]">
                reset password
              </Link>
              ) to set a new password.
            </p>
            <Link
              href="/login"
              className="mt-4 inline-flex text-sm font-semibold text-[var(--color-primary)]"
            >
              Back to sign in
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
            <Button type="submit" fullWidth disabled={loading}>
              {loading ? "Sending…" : "Send reset link"}
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
