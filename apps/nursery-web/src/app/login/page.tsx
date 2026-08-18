"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState, Suspense } from "react";
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

  const loginOtp = useAuthStore((s) => s.loginOtp);
  const sendOtp = useAuthStore((s) => s.sendOtp);
  const [mode, setMode] = useState<"password" | "otp">("password");
  const [identifier, setIdentifier] = useState("");
  const [password, setPassword] = useState("");
  const [otpCode, setOtpCode] = useState("");
  const [otpSent, setOtpSent] = useState(false);
  const [otpSending, setOtpSending] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [apiHealth, setApiHealth] = useState<ApiHealthState>({ status: "checking" });
  const [unlockAt, setUnlockAt] = useState<number | null>(null);
  const [now, setNow] = useState(() => Date.now());

  const showDemo = process.env.NODE_ENV === "development";
  const lockSeconds =
    unlockAt != null ? Math.max(0, Math.ceil((unlockAt - now) / 1000)) : 0;
  const isLocked = lockSeconds > 0;

  useEffect(() => {
    void probeApiHealth(resolveBrowserApiBaseUrl()).then(setApiHealth);
  }, []);

  useEffect(() => {
    if (!unlockAt) return;
    setNow(Date.now());
    const id = window.setInterval(() => {
      const t = Date.now();
      setNow(t);
      if (t >= unlockAt) {
        setUnlockAt(null);
      }
    }, 250);
    return () => window.clearInterval(id);
  }, [unlockAt]);

  async function handleSendOtp() {
    if (otpSending || loading) return;
    const mobile = identifier.trim();
    if (!/^\+?\d{10,15}$/.test(mobile.replace(/\s/g, ""))) {
      toast("Enter a valid mobile number", "error");
      return;
    }
    setOtpSending(true);
    try {
      await sendOtp(mobile, "login");
      setOtpSent(true);
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

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (loading || isLocked) {
      if (isLocked) toast(authRateLimitMessage(lockSeconds), "error");
      return;
    }
    if (apiHealth.status === "down") {
      toast(apiHealth.detail, "error");
      return;
    }
    try {
      let ok: boolean;
      if (mode === "otp") {
        ok = await loginOtp(identifier.trim(), otpCode.trim());
      } else {
        ok = await login(identifier.trim(), password);
      }
      if (!ok) return;
      await fetchCart();
      toast("Welcome back");
      router.push(next);
    } catch (err) {
      if (isAuthRateLimited(err)) {
        const seconds = resolveAuthLockSeconds(err);
        setUnlockAt(Date.now() + seconds * 1000);
        toast(authRateLimitMessage(seconds), "error");
        return;
      }
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
          {isLocked ? (
            <p
              className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
              role="alert"
              aria-live="polite"
            >
              Please wait{" "}
              <span className="font-semibold tabular-nums">
                {formatAuthCountdown(lockSeconds)}
              </span>
              , then try again.
            </p>
          ) : null}
          {apiHealth.status === "ok" && showDemo ? (
            <p className="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
              API ready · demo: asha@example.com / Secret@123 or mobile 9876543210
            </p>
          ) : null}
          <div className="flex gap-2 rounded-md bg-[var(--color-surface)] p-1">
            <button
              type="button"
              className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition ${mode === "password" ? "bg-white shadow-sm text-[var(--color-primary-deep)]" : "text-[var(--color-muted)]"}`}
              onClick={() => { setMode("password"); setOtpSent(false); setOtpCode(""); }}
            >
              Password
            </button>
            <button
              type="button"
              className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition ${mode === "otp" ? "bg-white shadow-sm text-[var(--color-primary-deep)]" : "text-[var(--color-muted)]"}`}
              onClick={() => setMode("otp")}
            >
              OTP
            </button>
          </div>
          <Field label={mode === "otp" ? "Mobile number" : "Email or mobile"} htmlFor="identifier">
            <Input
              id="identifier"
              type={mode === "otp" ? "tel" : "text"}
              autoComplete={mode === "otp" ? "tel" : "email tel"}
              placeholder={mode === "otp" ? "+91 98765 43210" : "Email or mobile number"}
              value={identifier}
              onChange={(e) => setIdentifier(e.target.value)}
              required
              disabled={isLocked}
            />
          </Field>
          {mode === "password" ? (
            <>
              <Field label="Password" htmlFor="password">
                <div className="relative">
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
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
              </Field>
              <div className="flex justify-end">
                <Link
                  href="/forgot-password"
                  className="text-sm font-semibold text-[var(--color-primary)]"
                >
                  Forgot password?
                </Link>
              </div>
            </>
          ) : (
            <div className="space-y-3">
              {!otpSent ? (
                <Button type="button" fullWidth variant="outline" onClick={handleSendOtp} disabled={otpSending || isLocked}>
                  {otpSending ? "Sending OTP…" : "Send OTP"}
                </Button>
              ) : (
                <>
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
                  <button
                    type="button"
                    className="text-xs font-semibold text-[var(--color-primary)]"
                    onClick={handleSendOtp}
                    disabled={otpSending || isLocked}
                  >
                    Resend OTP
                  </button>
                </>
              )}
            </div>
          )}
          <Button
            type="submit"
            fullWidth
            disabled={loading || isLocked || apiHealth.status === "down" || (mode === "otp" && !otpSent)}
          >
            {loading
              ? "Signing in…"
              : isLocked
                ? `Try again in ${formatAuthCountdown(lockSeconds)}`
                : "Sign in"}
          </Button>
          <p className="text-sm">
            New here?{" "}
            <Link href={registerHref} className="font-semibold text-[var(--color-primary)]">
              Create an account
            </Link>
          </p>
          {showDemo ? (
            <p className="text-sm text-[var(--color-muted)]">
              Demo (dev): asha@example.com / Secret@123 · OTP dev code: 123456
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
