"use client";

import { useToastStore } from "@/store/toast";
import { cn } from "@/lib/cn";

export function ToastViewport() {
  const { toasts, dismiss } = useToastStore();
  if (!toasts.length) return null;

  return (
    <div className="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={cn(
            "pointer-events-auto rounded-[var(--admin-radius)] border px-3 py-2 text-sm shadow-[var(--admin-shadow)]",
            toast.tone === "success" &&
              "border-[var(--admin-success)]/20 bg-[var(--admin-success-soft)] text-[var(--admin-success)]",
            toast.tone === "error" &&
              "border-[var(--admin-danger)]/20 bg-[var(--admin-danger-soft)] text-[var(--admin-danger)]",
            toast.tone === "warning" &&
              "border-[var(--admin-warning)]/20 bg-[var(--admin-warning-soft)] text-[var(--admin-warning)]",
            toast.tone === "info" &&
              "border-[var(--admin-info)]/20 bg-[var(--admin-info-soft)] text-[var(--admin-info)]",
          )}
        >
          <div className="flex items-start justify-between gap-3">
            <span>{toast.message}</span>
            <button
              type="button"
              className="text-xs opacity-70 hover:opacity-100"
              onClick={() => dismiss(toast.id)}
            >
              Close
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}
