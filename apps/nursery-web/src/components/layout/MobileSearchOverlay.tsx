"use client";

import { useUiStore } from "@/store/ui";
import { SearchBox } from "@/components/layout/SearchBox";

export function MobileSearchOverlay() {
  const open = useUiStore((s) => s.mobileSearchOpen);
  const close = useUiStore((s) => s.closeMobileSearch);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[var(--z-drawer)] bg-[var(--color-surface)] p-4 md:hidden animate-fade">
      <div className="mb-4 flex items-center justify-between">
        <p className="font-semibold">Search</p>
        <button type="button" onClick={close} className="inline-flex h-11 w-11 items-center justify-center text-2xl" aria-label="Close search">
          ×
        </button>
      </div>
      <SearchBox variant="mobile" autoFocus onNavigate={close} />
    </div>
  );
}
