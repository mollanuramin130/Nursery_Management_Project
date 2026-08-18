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
  orderNumber,
  payment = false,
}: {
  open: boolean;
  onClose: () => void;
  reference?: string;
  orderNumber?: string;
  payment?: boolean;
}) {
  const [copied, setCopied] = useState(false);
  const [hint, setHint] = useState<string | null>(null);
  const telOk = canUseTelHandler();

  if (!open) return null;

  async function onCopy() {
    const ok = await copySupportNumber();
    setCopied(ok);
    setHint(ok ? null : "Copy the number shown above.");
  }

  return (
    <div
      className="fixed inset-0 z-[var(--z-modal,80)] flex items-end justify-center bg-black/35 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="gl-support-title"
      onClick={onClose}
    >
      <div
        className="w-full max-w-md rounded-[var(--radius-xl,20px)] bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <h2 id="gl-support-title" className="display text-2xl text-[var(--color-primary-deep)]">
          GreenLeaf Support
        </h2>
        <p className="mt-2 text-sm text-[var(--color-muted)] leading-relaxed">
          {payment
            ? "Your payment could not be confirmed yet. Contacting support does not mean the payment succeeded."
            : "We're here to help."}
        </p>
        <p className="mt-4 text-sm font-semibold text-[var(--color-ink)]">
          Support: {GREENLEAF_SUPPORT_PHONE}
        </p>
        {reference ? (
          <p className="mt-1 text-sm font-semibold tracking-wide">Reference: {reference}</p>
        ) : null}
        {orderNumber ? (
          <p className="mt-1 text-sm">Order: {orderNumber}</p>
        ) : null}
        {reference ? (
          <p className="mt-2 text-xs text-[var(--color-muted)]">
            If you contact GreenLeaf Support, please mention this reference number.
          </p>
        ) : null}
        {hint ? <p className="mt-2 text-sm text-[var(--color-warning)]">{hint}</p> : null}
        <div className="mt-5 flex flex-col gap-2">
          {telOk ? (
            <a
              href={GREENLEAF_SUPPORT_TEL}
              className="inline-flex min-h-11 items-center justify-center rounded-full bg-[var(--color-primary-deep)] px-5 text-sm font-semibold text-white"
            >
              Call Support
            </a>
          ) : (
            <p className="text-sm text-[var(--color-muted)]">
              Call {GREENLEAF_SUPPORT_PHONE} from your phone, or copy the number.
            </p>
          )}
          <Button variant="outline" onClick={() => void onCopy()}>
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
