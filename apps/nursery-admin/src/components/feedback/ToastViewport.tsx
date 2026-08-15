"use client";

import { useToastStore } from "@/store/toast";
import { cn } from "@/lib/cn";

/** QA-38: solid contrast toasts, clear of sticky bottom action panels. */
export function ToastViewport() {
  const { toasts, dismiss } = useToastStore();
  if (!toasts.length) return null;

  return (
    <div className="pointer-events-none fixed bottom-20 right-4 z-[100] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2 sm:bottom-6">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role="status"
          className={cn(
            "pointer-events-auto rounded-[var(--admin-radius)] border px-3 py-2.5 text-sm font-medium shadow-[var(--admin-shadow)]",
            toast.tone === "success" &&
              "border-[var(--admin-success)] bg-[var(--admin-success)] text-white",
            toast.tone === "error" &&
              "border-[var(--admin-danger)] bg-[var(--admin-danger)] text-white",
            toast.tone === "warning" &&
              "border-[var(--admin-warning)] bg-[var(--admin-warning)] text-white",
            toast.tone === "info" &&
              "border-[var(--admin-info)] bg-[var(--admin-info)] text-white",
          )}
        >
          <div className="flex items-start justify-between gap-3">
            <span>{toast.message}</span>
            <button
              type="button"
              className="shrink-0 text-xs font-semibold text-white/90 hover:text-white"
              onClick={() => dismiss(toast.id)}
              aria-label="Dismiss notification"
            >
              Close
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}
