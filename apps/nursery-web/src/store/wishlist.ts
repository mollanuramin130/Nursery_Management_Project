"use client";

import { create } from "zustand";
import { apiGet, apiSend } from "@/lib/api";
import type { Cart, ProductSummary } from "@/lib/types";

export type WishRow = {
  wishlist_item_id?: number;
  id?: number;
  product: ProductSummary;
};

type WishlistState = {
  items: WishRow[];
  loaded: boolean;
  loading: boolean;
  error: string | null;
  fetchWishlist: () => Promise<void>;
  add: (productId: number) => Promise<void>;
  remove: (productId: number) => Promise<void>;
  moveToCart: (productId: number) => Promise<Cart>;
  has: (productId: number) => boolean;
  clearLocal: () => void;
};

export const useWishlistStore = create<WishlistState>((set, get) => ({
  items: [],
  loaded: false,
  loading: false,
  error: null,

  fetchWishlist: async () => {
    set({ loading: true, error: null });
    try {
      const res = await apiGet<WishRow[]>("/wishlist");
      set({ items: res.data ?? [], loaded: true, error: null });
    } catch (e) {
      // QA-36-004: do not present fetch failures as an empty wishlist.
      set({
        loaded: true,
        error: e instanceof Error ? e.message : "Failed to load wishlist",
      });
    } finally {
      set({ loading: false });
    }
  },

  add: async (productId) => {
    await apiSend("post", "/wishlist", { product_id: productId });
    await get().fetchWishlist();
  },

  remove: async (productId) => {
    // Optimistic remove — avoid stale row flash while DELETE is in flight (QA-36-008).
    const previous = get().items;
    set({ items: previous.filter((i) => i.product.id !== productId) });
    try {
      await apiSend("delete", `/wishlist/${productId}`);
    } catch (e) {
      set({ items: previous });
      throw e;
    }
  },

  moveToCart: async (productId) => {
    const res = await apiSend<{ cart: Cart; product_id: number }>(
      "post",
      `/wishlist/${productId}/move-to-cart`,
      { quantity: 1 },
    );
    set({ items: get().items.filter((i) => i.product.id !== productId) });
    return res.data.cart;
  },

  has: (productId) => get().items.some((i) => i.product.id === productId),

  clearLocal: () => set({ items: [], loaded: false, error: null }),
}));
