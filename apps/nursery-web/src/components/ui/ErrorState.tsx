"use client";

import { Button } from "@/components/ui/Button";

export function ErrorState({
  title = "Something went wrong while loading your garden",
  description = "Please check your connection and try again.",
  onRetry,
}: {
  title?: string;
  description?: string;
  onRetry?: () => void;
}) {
  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center animate-up">
      <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-error-soft)] text-[var(--color-error)]">
        !
      </div>
      <h2 className="display text-3xl text-[var(--color-primary-deep)]">{title}</h2>
      <p className="mt-3 text-[var(--color-muted)] leading-relaxed">{description}</p>
      {onRetry ? (
        <div className="mt-6">
          <Button onClick={onRetry}>Try again</Button>
        </div>
      ) : null}
    </div>
  );
}
