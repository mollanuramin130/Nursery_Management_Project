"use client";

import { cn } from "@/lib/cn";

type InputProps = React.InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  hint?: string;
  error?: string;
};

export function Input({ className, label, hint, error, id, ...props }: InputProps) {
  const inputId = id ?? props.name;
  return (
    <label className="flex flex-col gap-1.5 text-sm">
      {label ? <span className="font-medium text-[var(--admin-ink)]">{label}</span> : null}
      <input
        id={inputId}
        className={cn(
          "h-9 rounded-[var(--admin-radius)] border border-[var(--admin-border)] bg-white px-3 text-[var(--admin-ink)] outline-none placeholder:text-[var(--admin-muted)] focus:border-[var(--admin-primary)]",
          error && "border-[var(--admin-danger)]",
          className,
        )}
        {...props}
      />
      {error ? <span className="text-xs text-[var(--admin-danger)]">{error}</span> : null}
      {!error && hint ? <span className="text-xs text-[var(--admin-muted)]">{hint}</span> : null}
    </label>
  );
}

type TextAreaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label?: string;
};

export function TextArea({ className, label, id, ...props }: TextAreaProps) {
  const inputId = id ?? props.name;
  return (
    <label className="flex flex-col gap-1.5 text-sm">
      {label ? <span className="font-medium text-[var(--admin-ink)]">{label}</span> : null}
      <textarea
        id={inputId}
        className={cn(
          "min-h-24 rounded-[var(--admin-radius)] border border-[var(--admin-border)] bg-white px-3 py-2 text-[var(--admin-ink)] outline-none placeholder:text-[var(--admin-muted)] focus:border-[var(--admin-primary)]",
          className,
        )}
        {...props}
      />
    </label>
  );
}

type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
};

export function Select({ className, label, id, children, ...props }: SelectProps) {
  const inputId = id ?? props.name;
  return (
    <label className="flex flex-col gap-1.5 text-sm">
      {label ? <span className="font-medium text-[var(--admin-ink)]">{label}</span> : null}
      <select
        id={inputId}
        className={cn(
          "h-9 rounded-[var(--admin-radius)] border border-[var(--admin-border)] bg-white px-3 text-[var(--admin-ink)] outline-none focus:border-[var(--admin-primary)]",
          className,
        )}
        {...props}
      >
        {children}
      </select>
    </label>
  );
}
