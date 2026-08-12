"use client";

import { cn } from "@/lib/cn";

const STEPS = ["Address", "Delivery", "Payment", "Review"] as const;

export function CheckoutStepper({
  step,
  onStep,
}: {
  step: number;
  onStep?: (n: number) => void;
}) {
  return (
    <ol className="mb-6 flex items-center gap-1 sm:gap-2" aria-label="Checkout progress">
      {STEPS.map((label, i) => {
        const n = i + 1;
        const done = step > n;
        const current = step === n;
        return (
          <li key={label} className="flex flex-1 items-center gap-1 sm:gap-2">
            <button
              type="button"
              disabled={!onStep || n > step}
              onClick={() => onStep?.(n)}
              className={cn(
                "flex min-h-10 w-full flex-col items-center justify-center rounded-[var(--radius-md)] border px-1 py-2 text-center sm:flex-row sm:gap-2 sm:px-3",
                current && "border-[var(--color-primary)] bg-[var(--color-primary-soft)]",
                done && "border-[var(--color-primary)] text-[var(--color-primary-deep)]",
                !current && !done && "border-[var(--color-border)] text-[var(--color-muted)]",
              )}
            >
              <span
                className={cn(
                  "flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold",
                  current || done
                    ? "bg-[var(--color-primary-deep)] text-white"
                    : "bg-[var(--color-surface-muted)]",
                )}
              >
                {done ? "✓" : n}
              </span>
              <span className="mt-1 text-[10px] font-semibold sm:mt-0 sm:text-xs">{label}</span>
            </button>
          </li>
        );
      })}
    </ol>
  );
}
