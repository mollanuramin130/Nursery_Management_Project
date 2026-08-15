"use client";

import { useToastStore } from "@/store/toast";
import { cn } from "@/lib/cn";

/**
 * QA-38: sit above bottom nav + sticky commerce CTAs so toasts never cover
 * Add to Cart / Buy / Place Order / bottom navigation.
 */
export function ToastViewport() {
  const items = useToastStore((s) => s.items);
  const dismiss = useToastStore((s) => s.dismiss);

  if (!items.length) return null;

  return (
    <div
      className="pointer-events-none fixed bottom-[calc(var(--bottom-nav-h)+var(--sticky-cta-h)+1rem)] left-1/2 z-[var(--z-toast)] flex w-[min(420px,calc(100%-2rem))] -translate-x-1/2 flex-col gap-2 md:bottom-6"
      aria-live="polite"
    >
      {items.map((t) => (
        <div
          key={t.id}
          className={cn(
            "pointer-events-auto animate-up rounded-[var(--radius-md)] px-4 py-3 text-sm font-semibold shadow-[var(--shadow-md)]",
            t.kind === "success" && "bg-[var(--color-primary-deep)] text-white",
            t.kind === "error" && "bg-[var(--color-error)] text-white",
            t.kind === "info" && "bg-[var(--color-ink)] text-white",
          )}
        >
          <div className="flex items-start justify-between gap-3">
            <span>{t.message}</span>
            <button
              type="button"
              className="opacity-80 hover:opacity-100"
              onClick={() => dismiss(t.id)}
              aria-label="Dismiss notification"
            >
              ×
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}
