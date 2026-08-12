"use client";

import { create } from "zustand";
import { cartService } from "@/lib/services";
import type { Cart } from "@/lib/types";

type CartState = {
  cart: Cart | null;
  loading: boolean;
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

export const useCartStore = create<CartState>((set) => ({
  cart: null,
  loading: false,

  fetchCart: async () => {
    set({ loading: true });
    try {
      const res = await cartService.get();
      set({ cart: res.data });
    } catch {
      // Keep previous cart if any; otherwise empty — avoid silent total fabrication.
      set((s) => ({ cart: s.cart ?? emptyCart }));
    } finally {
      set({ loading: false });
    }
  },

  addItem: async (productId, quantity = 1) => {
    const res = await cartService.addItem(productId, quantity);
    set({ cart: res.data });
  },

  updateItem: async (itemId, quantity) => {
    const res = await cartService.updateItem(itemId, quantity);
    set({ cart: res.data });
  },

  removeItem: async (itemId) => {
    const res = await cartService.removeItem(itemId);
    set({ cart: res.data });
  },

  clearCart: async () => {
    const res = await cartService.clear();
    set({ cart: res.data });
  },

  moveToWishlist: async (itemId) => {
    const res = await cartService.moveToWishlist(itemId);
    set({ cart: res.data });
  },

  applyCoupon: async (code) => {
    const res = await cartService.applyCoupon(code);
    set({ cart: res.data });
  },

  removeCoupon: async () => {
    const res = await cartService.removeCoupon();
    set({ cart: res.data });
  },
}));
