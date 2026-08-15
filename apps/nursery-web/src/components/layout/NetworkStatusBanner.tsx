"use client";

import { useCallback, useEffect } from "react";
import { bannerCopy } from "@/lib/network-errors";
import { useNetworkStatusStore } from "@/store/network-status";

/**
 * QA-37 — non-blocking connection banner. Does not replace loaded page content.
 */
export function NetworkStatusBanner() {
  const showBanner = useNetworkStatusStore((s) => s.showBanner);
  const kind = useNetworkStatusStore((s) => s.kind);
  const message = useNetworkStatusStore((s) => s.message);
  const setBrowserOffline = useNetworkStatusStore((s) => s.setBrowserOffline);
  const setReconnecting = useNetworkStatusStore((s) => s.setReconnecting);
  const reportSuccess = useNetworkStatusStore((s) => s.reportSuccess);

  const probe = useCallback(async () => {
    setReconnecting();
    try {
      const res = await fetch("/api/bff/proxy/health/ready", {
        credentials: "include",
        cache: "no-store",
      });
      if (res.ok) {
        reportSuccess();
        return;
      }
    } catch {
      /* keep banner */
    }
    if (typeof navigator !== "undefined" && !navigator.onLine) {
      setBrowserOffline(true);
    }
  }, [reportSuccess, setBrowserOffline, setReconnecting]);

  useEffect(() => {
    const onOffline = () => setBrowserOffline(true);
    const onOnline = () => {
      setBrowserOffline(false);
      void probe();
    };
    window.addEventListener("offline", onOffline);
    window.addEventListener("online", onOnline);
    if (typeof navigator !== "undefined" && !navigator.onLine) {
      setBrowserOffline(true);
    }
    return () => {
      window.removeEventListener("offline", onOffline);
      window.removeEventListener("online", onOnline);
    };
  }, [probe, setBrowserOffline]);

  if (!showBanner) return null;

  const text = message || bannerCopy(kind);
  const offline = kind === "offline";

  return (
    <div
      role="status"
      aria-live="polite"
      className={`relative z-[var(--z-banner)] border-b px-4 py-2 text-sm ${
        offline
          ? "border-[var(--color-warning)] bg-[var(--color-warning-soft)] text-[var(--color-ink)]"
          : "border-[var(--color-border)] bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]"
      }`}
    >
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <p className="font-semibold">{text}</p>
        <button
          type="button"
          className="shrink-0 text-sm font-bold underline-offset-2 hover:underline"
          onClick={() => void probe()}
        >
          Retry
        </button>
      </div>
    </div>
  );
}
