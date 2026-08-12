"use client";

import { cn } from "@/lib/cn";

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "primary" | "secondary" | "ghost" | "danger";
  size?: "sm" | "md";
};

export function Button({
  className,
  variant = "primary",
  size = "md",
  type = "button",
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        "inline-flex items-center justify-center gap-2 rounded-[var(--admin-radius)] font-medium transition disabled:cursor-not-allowed disabled:opacity-50",
        size === "sm" ? "h-8 px-3 text-xs" : "h-9 px-3.5 text-sm",
        variant === "primary" &&
          "bg-[var(--admin-primary)] text-white hover:bg-[var(--admin-primary-hover)]",
        variant === "secondary" &&
          "border border-[var(--admin-border)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-surface-muted)]",
        variant === "ghost" && "text-[var(--admin-ink-soft)] hover:bg-[var(--admin-surface-muted)]",
        variant === "danger" &&
          "bg-[var(--admin-danger)] text-white hover:brightness-95",
        className,
      )}
      {...props}
    />
  );
}
