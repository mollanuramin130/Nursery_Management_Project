"use client";

import { create } from "zustand";

type UiState = {
  miniCartOpen: boolean;
  mobileSearchOpen: boolean;
  lastAddedName: string | null;
  openMiniCart: (productName?: string) => void;
  closeMiniCart: () => void;
  openMobileSearch: () => void;
  closeMobileSearch: () => void;
};

export const useUiStore = create<UiState>((set) => ({
  miniCartOpen: false,
  mobileSearchOpen: false,
  lastAddedName: null,
  openMiniCart: (productName) =>
    set({ miniCartOpen: true, lastAddedName: productName ?? null }),
  closeMiniCart: () => set({ miniCartOpen: false }),
  openMobileSearch: () => set({ mobileSearchOpen: true }),
  closeMobileSearch: () => set({ mobileSearchOpen: false }),
}));
