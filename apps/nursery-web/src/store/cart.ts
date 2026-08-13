"use client";

import { create } from "zustand";
import { cartService } from "@/lib/services";
import type { Cart } from "@/lib/types";

type CartState = {
  cart: Cart | null;
  loading: boolean;
  mutating: boolean;
  /** QA-37-004: fetch failure ≠ empty cart. */
  error: string | null;
  fetchCart: () => Promise<void>;
  addItem: (productId: number, quantity?: number) => Promise<void>;
  updateItem: (itemId: number, quantity: number) => Promise<void>;
  removeItem: (itemId: number) => Promise<void>;
  clearCart: () => Promise<void>;
  moveToWishlist: (itemId: number) => Promise<void>;
  applyCoupon: (code: string) => Promise<void>;
  removeCoupon: () => Promise<void>;
};

const emptyCart: Cart = {
  currency: "INR",
  items: [],
  item_count: 0,
  subtotal: 0,
  discount_total: 0,
  tax_total: 0,
  shipping_total: 0,
  grand_total: 0,
  free_delivery: {
    enabled: true,
    threshold: 999,
    remaining: 999,
    qualifies: false,
  },
};

export const useCartStore = create<CartState>((set, get) => ({
  cart: null,
  loading: false,
  mutating: false,
  error: null,

  fetchCart: async () => {
    set({ loading: true, error: null });
    try {
      const res = await cartService.get();
      set({ cart: res.data, error: null });
    } catch (e) {
      // Keep previous cart if any; never invent empty on first failure.
      set({
        error: e instanceof Error ? e.message : "Unable to load cart",
        cart: get().cart,
      });
    } finally {
      set({ loading: false });
    }
  },

  addItem: async (productId, quantity = 1) => {
    const res = await cartService.addItem(productId, quantity);
    set({ cart: res.data, error: null });
  },

  updateItem: async (itemId, quantity) => {
    // QA-37-002: single-flight cart line mutations.
    if (get().mutating) return;
    set({ mutating: true });
    try {
      const res = await cartService.updateItem(itemId, quantity);
      set({ cart: res.data, error: null });
    } finally {
      set({ mutating: false });
    }
  },

  removeItem: async (itemId) => {
    if (get().mutating) return;
    set({ mutating: true });
    try {
      const res = await cartService.removeItem(itemId);
      set({ cart: res.data, error: null });
    } finally {
      set({ mutating: false });
    }
  },

  clearCart: async () => {
    if (get().mutating) return;
    set({ mutating: true });
    try {
      const res = await cartService.clear();
      set({ cart: res.data, error: null });
    } finally {
      set({ mutating: false });
    }
  },

  moveToWishlist: async (itemId) => {
    if (get().mutating) return;
    set({ mutating: true });
    try {
      const res = await cartService.moveToWishlist(itemId);
      set({ cart: res.data, error: null });
    } finally {
      set({ mutating: false });
    }
  },

  applyCoupon: async (code) => {
    const res = await cartService.applyCoupon(code);
    set({ cart: res.data, error: null });
  },

  removeCoupon: async () => {
    const res = await cartService.removeCoupon();
    set({ cart: res.data, error: null });
  },
}));

/** Exported for unit checks — empty fallback must not be used as fetch-error UX. */
export const __cartEmptyFallback = emptyCart;
