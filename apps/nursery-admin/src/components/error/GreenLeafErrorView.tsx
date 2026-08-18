"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SupportContactDialog } from "@/components/error/SupportContactDialog";
import { newErrorReference } from "@/lib/support";

export function GreenLeafErrorView({
  title = "Something went wrong",
  message = "Something went wrong while loading this section. You can try again or contact support if it continues.",
  reference,
  onRetry,
}: {
  title?: string;
  message?: string;
  reference?: string;
  onRetry?: () => void;
}) {
  const ref = useMemo(() => reference ?? newErrorReference(), [reference]);
  const [helpOpen, setHelpOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--admin-primary-soft)] text-2xl">
        🌿
      </div>
      <h2 className="text-lg font-semibold text-[var(--admin-ink)]">{title}</h2>
      <p className="mt-2 text-sm text-[var(--admin-muted)] leading-relaxed">{message}</p>
      <p className="mt-3 text-sm font-semibold tracking-wide">Reference: {ref}</p>
      <div className="mt-5 flex flex-col items-center gap-2">
        {onRetry ? (
          <Button
            onClick={() => {
              if (busy) return;
              setBusy(true);
              onRetry();
              window.setTimeout(() => setBusy(false), 450);
            }}
            disabled={busy}
          >
            {busy ? "Retrying…" : "Try Again"}
          </Button>
        ) : null}
        <Button variant="secondary" onClick={() => setHelpOpen(true)}>
          Contact Support
        </Button>
      </div>
      <SupportContactDialog
        open={helpOpen}
        onClose={() => setHelpOpen(false)}
        reference={ref}
      />
    </div>
  );
}
