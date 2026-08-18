"use client";

import Link from "next/link";
import { FormEvent, Suspense, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { sanitizeNext } from "@/lib/auth-redirect";
import {
  authRateLimitMessage,
  authUserMessage,
  formatAuthCountdown,
  isAuthRateLimited,
  resolveAuthLockSeconds,
} from "@/lib/auth-messages";
import { isPasswordValid, PASSWORD_HINT, passwordRequirementErrors } from "@/lib/password-rules";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";

function RegisterForm() {
  const register = useAuthStore((s) => s.register);
  const sendOtp = useAuthStore((s) => s.sendOtp);
  const verifyOtp = useAuthStore((s) => s.verifyOtp);
  const loading = useAuthStore((s) => s.loading);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = useMemo(() => sanitizeNext(searchParams.get("next")), [searchParams]);

  const [step, setStep] = useState<"mobile" | "otp" | "details">("mobile");
  const [mobile, setMobile] = useState("");
  const [otpCode, setOtpCode] = useState("");
  const [otpVerifiedToken, setOtpVerifiedToken] = useState("");
  const [otpSending, setOtpSending] = useState(false);
  const [otpVerifying, setOtpVerifying] = useState(false);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [unlockAt, setUnlockAt] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());

  const lockSeconds =
    unlockAt != null ? Math.max(0, Math.ceil((unlockAt - now) / 1000)) : 0;
  const isLocked = lockSeconds > 0;
  const reqErrors = passwordRequirementErrors(password);

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

  async function handleSendOtp() {
    if (otpSending) return;
    const m = mobile.trim();
    if (!/^\+?\d{10,15}$/.test(m.replace(/\s/g, ""))) {
      toast("Enter a valid mobile number", "error");
      return;
    }
    setOtpSending(true);
    try {
      await sendOtp(m, "register");
      setStep("otp");
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

  async function handleVerifyOtp() {
    if (otpVerifying) return;
    setOtpVerifying(true);
    try {
      const res = await verifyOtp(mobile.trim(), otpCode.trim(), "register");
      setOtpVerifiedToken(res.otp_verified_token);
      setStep("details");
      toast("Mobile verified!");
    } catch (err) {
      toast(authUserMessage(err, "Verification failed"), "error");
    } finally {
      setOtpVerifying(false);
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (step === "mobile") {
      handleSendOtp();
      return;
    }
    if (step === "otp") {
      handleVerifyOtp();
      return;
    }
    if (loading || isLocked) {
      if (isLocked) toast(authRateLimitMessage(lockSeconds), "error");
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
      await register({
        name,
        mobile: mobile.trim(),
        email: email || undefined,
        otp_verified_token: otpVerifiedToken,
        password,
        password_confirmation: confirm,
      });
      await fetchCart();
      toast("Welcome to the nursery");
      router.push(next);
    } catch (err) {
      if (isAuthRateLimited(err)) {
        const seconds = resolveAuthLockSeconds(err);
        setUnlockAt(Date.now() + seconds * 1000);
        toast(authRateLimitMessage(seconds), "error");
        return;
      }
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
          {isLocked ? (
            <p
              className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
              role="alert"
              aria-live="polite"
            >
              Too many attempts. Try again in{" "}
              <span className="font-semibold tabular-nums">
                {formatAuthCountdown(lockSeconds)}
              </span>
              .
            </p>
          ) : null}

          {/* Step indicator */}
          <div className="flex items-center gap-2 text-xs text-[var(--color-muted)]">
            <span className={step === "mobile" ? "font-bold text-[var(--color-primary)]" : step !== "mobile" ? "text-emerald-600" : ""}>1. Mobile</span>
            <span>→</span>
            <span className={step === "otp" ? "font-bold text-[var(--color-primary)]" : step === "details" ? "text-emerald-600" : ""}>2. Verify</span>
            <span>→</span>
            <span className={step === "details" ? "font-bold text-[var(--color-primary)]" : ""}>3. Details</span>
          </div>

          {step === "mobile" ? (
            <>
              <Field label="Mobile number" htmlFor="mobile">
                <Input
                  id="mobile"
                  type="tel"
                  autoComplete="tel"
                  placeholder="+91 98765 43210"
                  value={mobile}
                  onChange={(e) => setMobile(e.target.value)}
                  required
                  disabled={isLocked}
                />
              </Field>
              <Button type="submit" fullWidth disabled={otpSending || isLocked}>
                {otpSending ? "Sending OTP…" : "Send verification code"}
              </Button>
            </>
          ) : step === "otp" ? (
            <>
              <p className="text-sm text-[var(--color-muted)]">
                Code sent to <span className="font-medium">{mobile}</span>
              </p>
              <Field label="Enter OTP" htmlFor="otp">
                <Input
                  id="otp"
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={6}
                  placeholder="6-digit code"
                  value={otpCode}
                  onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                  required
                  disabled={isLocked}
                />
              </Field>
              <div className="flex items-center justify-between">
                <button
                  type="button"
                  className="text-xs font-semibold text-[var(--color-primary)]"
                  onClick={() => { setStep("mobile"); setOtpCode(""); }}
                >
                  Change number
                </button>
                <button
                  type="button"
                  className="text-xs font-semibold text-[var(--color-primary)]"
                  onClick={handleSendOtp}
                  disabled={otpSending || isLocked}
                >
                  Resend OTP
                </button>
              </div>
              <Button type="submit" fullWidth disabled={otpVerifying || otpCode.length < 6 || isLocked}>
                {otpVerifying ? "Verifying…" : "Verify mobile"}
              </Button>
            </>
          ) : (
            <>
              <p className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Mobile verified: {mobile}
              </p>
              <Field label="Full name" htmlFor="name">
                <Input
                  id="name"
                  autoComplete="name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  disabled={isLocked}
                />
              </Field>
              <Field label="Email (optional)" htmlFor="email">
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={isLocked}
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
                    disabled={isLocked}
                  />
                  <button
                    type="button"
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[var(--color-primary)]"
                    onClick={() => setShowPassword((v) => !v)}
                    disabled={isLocked}
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
                  disabled={isLocked}
                />
              </Field>
              <Button type="submit" fullWidth disabled={loading || isLocked}>
                {loading
                  ? "Creating account…"
                  : isLocked
                    ? `Try again in ${formatAuthCountdown(lockSeconds)}`
                    : "Create account"}
              </Button>
            </>
          )}
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
