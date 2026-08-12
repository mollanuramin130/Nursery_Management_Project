"use client";

import { useEffect } from "react";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useWishlistStore } from "@/store/wishlist";
import { ToastViewport } from "@/components/ui/ToastViewport";
import { MiniCartDrawer } from "@/components/cart/MiniCartDrawer";
import { MobileSearchOverlay } from "@/components/layout/MobileSearchOverlay";

export function Providers({ children }: { children: React.ReactNode }) {
  const bootstrap = useAuthStore((s) => s.bootstrap);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const user = useAuthStore((s) => s.user);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const fetchWishlist = useWishlistStore((s) => s.fetchWishlist);
  const clearWish = useWishlistStore((s) => s.clearLocal);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  useEffect(() => {
    if (!bootstrapped) return;
    void fetchCart();
    if (user) void fetchWishlist();
    else clearWish();
  }, [bootstrapped, user, fetchCart, fetchWishlist, clearWish]);

  return (
    <>
      {children}
      <MiniCartDrawer />
      <MobileSearchOverlay />
      <ToastViewport />
    </>
  );
}
