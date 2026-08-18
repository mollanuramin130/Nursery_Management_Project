"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SupportContactDialog } from "@/components/error/SupportContactDialog";
import { newErrorReference } from "@/lib/support";

export function GreenLeafErrorView({
  title = "Something went wrong",
  message = "We're sorry, something unexpected happened while loading this part of GreenLeaf. You can try again or contact our support team if the problem continues.",
  reference,
  onRetry,
  payment = false,
  orderNumber,
}: {
  title?: string;
  message?: string;
  reference?: string;
  onRetry?: () => void;
  payment?: boolean;
  orderNumber?: string;
}) {
  const ref = useMemo(() => reference ?? newErrorReference(), [reference]);
  const [helpOpen, setHelpOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  function retry() {
    if (!onRetry || busy) return;
    setBusy(true);
    onRetry();
    window.setTimeout(() => setBusy(false), 450);
  }

  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-primary-soft)] text-3xl">
        🌿
      </div>
      <h2 className="display text-3xl text-[var(--color-primary-deep)]">{title}</h2>
      <p className="mt-3 text-[var(--color-muted)] leading-relaxed">{message}</p>
      <p className="mt-4 text-sm font-semibold tracking-wide text-[var(--color-ink-soft)]">
        Reference: {ref}
      </p>
      <p className="mt-1 text-xs text-[var(--color-muted)]">
        If you contact GreenLeaf Support, please mention this reference number.
      </p>
      <div className="mt-6 flex flex-col items-center gap-2">
        {onRetry ? (
          <Button onClick={retry} disabled={busy}>
            {busy ? "Retrying…" : "Try Again"}
          </Button>
        ) : null}
        <Button variant="outline" onClick={() => setHelpOpen(true)}>
          Contact GreenLeaf Support
        </Button>
      </div>
      <SupportContactDialog
        open={helpOpen}
        onClose={() => setHelpOpen(false)}
        reference={ref}
        orderNumber={orderNumber}
        payment={payment}
      />
    </div>
  );
}
