import { cn } from "@/lib/cn";

const tones = {
  neutral: "bg-[var(--admin-surface-muted)] text-[var(--admin-ink-soft)]",
  success: "bg-[var(--admin-success-soft)] text-[var(--admin-success)]",
  warning: "bg-[var(--admin-warning-soft)] text-[var(--admin-warning)]",
  danger: "bg-[var(--admin-danger-soft)] text-[var(--admin-danger)]",
  info: "bg-[var(--admin-info-soft)] text-[var(--admin-info)]",
} as const;

export function Badge({
  children,
  tone = "neutral",
  className,
}: {
  children: React.ReactNode;
  tone?: keyof typeof tones;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide",
        tones[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
