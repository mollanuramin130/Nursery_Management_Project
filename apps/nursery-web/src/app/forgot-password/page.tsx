"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import {
  authRateLimitMessage,
  authUserMessage,
  formatAuthCountdown,
  isAuthRateLimited,
  resolveAuthLockSeconds,
} from "@/lib/auth-messages";
import { isPasswordValid, PASSWORD_HINT } from "@/lib/password-rules";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function ForgotPasswordPage() {
  const forgotPassword = useAuthStore((s) => s.forgotPassword);
  const sendOtp = useAuthStore((s) => s.sendOtp);
  const verifyOtp = useAuthStore((s) => s.verifyOtp);
  const resetPasswordViaMobile = useAuthStore((s) => s.resetPasswordViaMobile);
  const loading = useAuthStore((s) => s.loading);
  const toast = useToastStore((s) => s.push);
  const [mode, setMode] = useState<"email" | "mobile">("mobile");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [mobileStep, setMobileStep] = useState<"input" | "otp" | "newpw" | "done">("input");
  const [otpCode, setOtpCode] = useState("");
  const [otpVerifiedToken, setOtpVerifiedToken] = useState("");
  const [newPw, setNewPw] = useState("");
  const [confirmPw, setConfirmPw] = useState("");
  const [otpSending, setOtpSending] = useState(false);
  const [otpVerifying, setOtpVerifying] = useState(false);
  const [sent, setSent] = useState(false);
  const [unlockAt, setUnlockAt] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());

  const lockSeconds =
    unlockAt != null ? Math.max(0, Math.ceil((unlockAt - now) / 1000)) : 0;
  const isLocked = lockSeconds > 0;

  useEffect(() => {
    if (!unlockAt) return;
    setNow(Date.now());
    const id = window.setInterval(() => {
      const t = Date.now();
      setNow(t);
      if (t >= unlockAt) setUnlockAt(null);
    }, 250);
    return () => window.clearInterval(id);
  }, [unlockAt]);

  async function handleMobileSendOtp() {
    if (otpSending) return;
    const m = mobile.trim();
    if (!/^\+?\d{10,15}$/.test(m.replace(/\s/g, ""))) {
      toast("Enter a valid mobile number", "error");
      return;
    }
    setOtpSending(true);
    try {
      await sendOtp(m, "reset");
      setMobileStep("otp");
      toast("OTP sent to your mobile");
    } catch (err) {
      if (isAuthRateLimited(err)) {
        const seconds = resolveAuthLockSeconds(err);
        setUnlockAt(Date.now() + seconds * 1000);
        toast(authRateLimitMessage(seconds), "error");
        return;
      }
      toast(authUserMessage(err, "Could not send OTP"), "error");
    } finally {
      setOtpSending(false);
    }
  }

  async function handleMobileVerifyOtp() {
    if (otpVerifying) return;
    setOtpVerifying(true);
    try {
      const res = await verifyOtp(mobile.trim(), otpCode.trim(), "reset");
      setOtpVerifiedToken(res.otp_verified_token);
      setMobileStep("newpw");
      toast("Mobile verified");
    } catch (err) {
      toast(authUserMessage(err, "Verification failed"), "error");
    } finally {
      setOtpVerifying(false);
    }
  }

  async function handleMobileResetPassword(e: FormEvent) {
    e.preventDefault();
    if (loading || isLocked) return;
    if (!isPasswordValid(newPw)) { toast(PASSWORD_HINT, "error"); return; }
    if (newPw !== confirmPw) { toast("Passwords do not match", "error"); return; }
    try {
      await resetPasswordViaMobile(mobile.trim(), otpVerifiedToken, newPw, confirmPw);
      setMobileStep("done");
      toast("Password reset successful");
    } catch (err) {
      if (isAuthRateLimited(err)) {
        const seconds = resolveAuthLockSeconds(err);
        setUnlockAt(Date.now() + seconds * 1000);
        toast(authRateLimitMessage(seconds), "error");
        return;
      }
      toast(authUserMessage(err, "Reset failed"), "error");
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (mode === "mobile") {
      if (mobileStep === "input") { handleMobileSendOtp(); return; }
      if (mobileStep === "otp") { handleMobileVerifyOtp(); return; }
      if (mobileStep === "newpw") { handleMobileResetPassword(e); return; }
      return;
    }
    if (loading || isLocked) {
      if (isLocked) toast(authRateLimitMessage(lockSeconds), "error");
      return;
    }
    try {
      await forgotPassword(email.trim());
      setSent(true);
      toast("If that email exists, a reset link was sent.");
    } catch (err) {
      if (isAuthRateLimited(err)) {
        const seconds = resolveAuthLockSeconds(err);
        setUnlockAt(Date.now() + seconds * 1000);
        toast(authRateLimitMessage(seconds), "error");
        return;
      }
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
            {isLocked ? (
              <p
                className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
                role="alert"
                aria-live="polite"
              >
                Too many reset requests. Try again in{" "}
                <span className="font-semibold tabular-nums">
                  {formatAuthCountdown(lockSeconds)}
                </span>
                .
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
                disabled={isLocked}
              />
            </Field>
            <Button type="submit" fullWidth disabled={loading || isLocked}>
              {loading
                ? "Sending…"
                : isLocked
                  ? `Try again in ${formatAuthCountdown(lockSeconds)}`
                  : "Send reset link"}
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
