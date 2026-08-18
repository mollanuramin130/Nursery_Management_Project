"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { SupportContactDialog } from "@/components/error/SupportContactDialog";
import type { ErrorCategory } from "@/lib/error-category";
import { shouldOfferSupport } from "@/lib/error-category";

export function ErrorState({
  title = "Something went wrong while loading your garden",
  description = "Please check your connection and try again.",
  onRetry,
  category = "temporaryNetwork",
  retryCount = 0,
  orderNumber,
}: {
  title?: string;
  description?: string;
  onRetry?: () => void;
  category?: ErrorCategory;
  retryCount?: number;
  orderNumber?: string;
}) {
  const [busy, setBusy] = useState(false);
  const [helpOpen, setHelpOpen] = useState(false);
  const offerHelp = shouldOfferSupport(category, { retryCount });

  function retry() {
    if (!onRetry || busy) return;
    setBusy(true);
    onRetry();
    window.setTimeout(() => setBusy(false), 450);
  }

  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center animate-up">
      <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-primary-soft)] text-3xl">
        🌿
      </div>
      <h2 className="display text-3xl text-[var(--color-primary-deep)]">{title}</h2>
      <p className="mt-3 text-[var(--color-muted)] leading-relaxed">{description}</p>
      {onRetry ? (
        <div className="mt-6">
          <Button onClick={retry} disabled={busy}>
            {busy ? "Retrying…" : "Try again"}
          </Button>
        </div>
      ) : null}
      {offerHelp ? (
        <div className="mt-3">
          <Button variant="ghost" onClick={() => setHelpOpen(true)}>
            Need Help?
          </Button>
        </div>
      ) : null}
      <SupportContactDialog
        open={helpOpen}
        onClose={() => setHelpOpen(false)}
        orderNumber={orderNumber}
        payment={category === "payment"}
      />
    </div>
  );
}
