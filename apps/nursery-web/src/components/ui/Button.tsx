import { cn } from "@/lib/cn";
import type { ButtonHTMLAttributes, ReactNode } from "react";

type Variant = "primary" | "secondary" | "outline" | "ghost" | "danger";
type Size = "sm" | "md" | "lg";

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant;
  size?: Size;
  fullWidth?: boolean;
  children: ReactNode;
};

const variants: Record<Variant, string> = {
  primary:
    "bg-[var(--color-primary-deep)] text-white hover:bg-[var(--color-primary-hover)] active:bg-[var(--color-primary-hover)] disabled:bg-[var(--color-border-strong)] disabled:text-[var(--color-text-disabled)] disabled:opacity-100 disabled:saturate-100",
  secondary:
    "bg-[var(--color-secondary-soft)] text-[var(--color-ink)] hover:bg-[var(--color-secondary-soft)] hover:brightness-[0.97] disabled:text-[var(--color-text-disabled)] disabled:opacity-100",
  outline:
    "bg-transparent border border-[var(--color-border-strong)] text-[var(--color-ink)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary-deep)] disabled:text-[var(--color-text-disabled)] disabled:opacity-100",
  ghost:
    "bg-transparent text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] disabled:text-[var(--color-text-disabled)] disabled:opacity-100",
  danger:
    "bg-[var(--color-error)] text-white hover:brightness-95 disabled:bg-[var(--color-error-soft)] disabled:text-[var(--color-error)] disabled:opacity-100 disabled:saturate-100",
};

const sizes: Record<Size, string> = {
  sm: "min-h-10 px-3.5 text-sm",
  md: "min-h-11 px-5 text-sm",
  lg: "min-h-12 px-6 text-base",
};

export function Button({
  variant = "primary",
  size = "md",
  fullWidth,
  className,
  children,
  type = "button",
  ...rest
}: Props) {
  return (
    <button
      type={type}
      className={cn(
        "inline-flex items-center justify-center gap-2 rounded-[var(--radius-full)] font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)] disabled:cursor-not-allowed",
        variants[variant],
        sizes[size],
        fullWidth && "w-full",
        className,
      )}
      {...rest}
    >
      {children}
    </button>
  );
}
