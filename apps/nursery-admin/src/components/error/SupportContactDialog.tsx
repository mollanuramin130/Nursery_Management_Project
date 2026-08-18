"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import {
  GREENLEAF_SUPPORT_PHONE,
  GREENLEAF_SUPPORT_TEL,
  canUseTelHandler,
  copySupportNumber,
} from "@/lib/support";

export function SupportContactDialog({
  open,
  onClose,
  reference,
}: {
  open: boolean;
  onClose: () => void;
  reference?: string;
}) {
  const [copied, setCopied] = useState(false);
  const [hint, setHint] = useState<string | null>(null);
  const telOk = canUseTelHandler();
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      onClick={onClose}
    >
      <div
        className="w-full max-w-md rounded-[var(--admin-radius-lg)] bg-white p-5 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <h2 className="text-base font-semibold text-[var(--admin-ink)]">GreenLeaf Support</h2>
        <p className="mt-1 text-sm text-[var(--admin-muted)]">We're here to help.</p>
        <p className="mt-3 text-sm font-semibold">Support: {GREENLEAF_SUPPORT_PHONE}</p>
        {reference ? (
          <p className="mt-1 text-sm font-semibold">Reference: {reference}</p>
        ) : null}
        {hint ? <p className="mt-2 text-sm">{hint}</p> : null}
        <div className="mt-4 flex flex-col gap-2">
          {telOk ? (
            <a
              href={GREENLEAF_SUPPORT_TEL}
              className="inline-flex h-9 items-center justify-center rounded-[var(--admin-radius)] bg-[var(--admin-primary)] text-sm font-medium text-white"
            >
              Call Support
            </a>
          ) : (
            <p className="text-sm text-[var(--admin-muted)]">
              Call {GREENLEAF_SUPPORT_PHONE} from your phone, or copy the number.
            </p>
          )}
          <Button
            variant="secondary"
            onClick={async () => {
              const ok = await copySupportNumber();
              setCopied(ok);
              setHint(ok ? null : "Copy the number shown above.");
            }}
          >
            {copied ? "Number copied" : "Copy Number"}
          </Button>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
        </div>
      </div>
    </div>
  );
}
