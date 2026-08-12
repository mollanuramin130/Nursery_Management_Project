"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import { SearchBox } from "@/components/layout/SearchBox";
import { apiGet } from "@/lib/api";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useUiStore } from "@/store/ui";
import { useWishlistStore } from "@/store/wishlist";
import { cn } from "@/lib/cn";

const storeName = process.env.NEXT_PUBLIC_STORE_NAME ?? "GreenLeaf Nursery";

export function SiteHeader() {
  const pathname = usePathname();
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const itemCount = useCartStore((s) => s.cart?.item_count ?? 0);
  const openMiniCart = useUiStore((s) => s.openMiniCart);
  const openMobileSearch = useUiStore((s) => s.openMobileSearch);
  const wishCount = useWishlistStore((s) => s.items.length);
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    if (!bootstrapped || !user) {
      setUnread(0);
      return;
    }
    let cancelled = false;
    const load = async () => {
      try {
        const res = await apiGet<{ unread_count: number }>("/notifications/unread-count");
        if (!cancelled) setUnread(res.data?.unread_count ?? 0);
      } catch {
        if (!cancelled) setUnread(0);
      }
    };
    void load();
    const t = window.setInterval(() => void load(), 60000);
    return () => {
      cancelled = true;
      window.clearInterval(t);
    };
  }, [bootstrapped, user, pathname]);

  const greeting = useMemo(() => {
    if (!user) return "Sign in";
    return `Hi, ${user.name.split(" ")[0]}`;
  }, [user]);

  return (
    <header className="sticky top-0 z-[var(--z-sticky)] border-b border-[var(--color-border)] bg-[var(--color-surface)]/95 backdrop-blur-md">
      <div className="container flex items-center gap-3 py-3 md:gap-5 md:py-3.5">
        <Link
          href="/"
          className="display shrink-0 text-[1.35rem] leading-none text-[var(--color-primary-deep)] md:text-[1.6rem]"
        >
          {storeName}
        </Link>

        <div className="relative mx-auto hidden min-w-0 flex-1 max-w-xl md:block">
          <SearchBox variant="desktop" />
        </div>

        <div className="ml-auto flex items-center gap-1 sm:gap-2">
          <button
            type="button"
            className="flex h-11 w-11 items-center justify-center rounded-full text-[var(--color-ink-soft)] hover:bg-[var(--color-primary-soft)] md:hidden"
            aria-label="Search"
            onClick={openMobileSearch}
          >
            ⌕
          </button>
          <span className="hidden text-sm text-[var(--color-muted)] lg:inline" title="Delivery region">
            Deliver to India
          </span>
          <Link
            href="/wishlist"
            className={cn(
              "relative hidden h-11 items-center gap-1 rounded-full px-3 text-sm font-semibold hover:bg-[var(--color-primary-soft)] sm:inline-flex",
              pathname === "/wishlist" && "text-[var(--color-primary-deep)]",
            )}
          >
            Wishlist
            {wishCount > 0 ? (
              <span className="rounded-full bg-[var(--color-primary-soft)] px-1.5 text-xs">{wishCount}</span>
            ) : null}
          </Link>
          {user ? (
            <Link
              href="/account/notifications"
              className={cn(
                "relative inline-flex h-11 items-center gap-1 rounded-full px-3 text-sm font-semibold hover:bg-[var(--color-primary-soft)]",
                pathname?.startsWith("/account/notifications") && "text-[var(--color-primary-deep)]",
              )}
              aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
            >
              <span className="md:hidden" aria-hidden>
                ●
              </span>
              <span className="hidden md:inline">Alerts</span>
              {unread > 0 ? (
                <span className="rounded-full bg-[var(--color-primary-deep)] px-1.5 text-xs text-white">
                  {unread > 99 ? "99+" : unread}
                </span>
              ) : null}
            </Link>
          ) : null}
          <Link
            href={user ? "/account" : "/login"}
            className="hidden h-11 items-center rounded-full px-3 text-sm font-semibold hover:bg-[var(--color-primary-soft)] sm:inline-flex"
          >
            {greeting}
          </Link>
          <button
            type="button"
            onClick={() => openMiniCart()}
            className="relative inline-flex h-11 items-center gap-2 rounded-full bg-[var(--color-primary-deep)] px-3.5 text-sm font-semibold text-white hover:bg-[var(--color-primary)]"
          >
            Cart
            {itemCount > 0 ? (
              <span className="rounded-full bg-white/20 px-1.5 text-xs">{itemCount}</span>
            ) : null}
          </button>
        </div>
      </div>
    </header>
  );
}
