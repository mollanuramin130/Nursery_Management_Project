import { cn } from "@/lib/cn";

type Tone = "neutral" | "success" | "warning" | "error" | "sale" | "brand";

const tones: Record<Tone, string> = {
  neutral: "bg-[var(--color-surface-muted)] text-[var(--color-ink-soft)]",
  success: "bg-[var(--color-success-soft)] text-[var(--color-success)]",
  warning: "bg-[var(--color-warning-soft)] text-[var(--color-warning)]",
  error: "bg-[var(--color-error-soft)] text-[var(--color-error)]",
  sale: "bg-[var(--color-error)] text-white",
  brand: "bg-[var(--color-primary-soft)] text-[var(--color-primary-deep)]",
};

export function Badge({
  children,
  tone = "neutral",
  className,
}: {
  children: React.ReactNode;
  tone?: Tone;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-[var(--radius-sm)] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide",
        tones[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
