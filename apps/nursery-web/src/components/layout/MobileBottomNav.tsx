"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCartStore } from "@/store/cart";
import { cn } from "@/lib/cn";

const items = [
  { href: "/", label: "Home", icon: "⌂" },
  { href: "/shop", label: "Shop", icon: "⚘" },
  { href: "/cart", label: "Cart", icon: "👜" },
  { href: "/wishlist", label: "Saved", icon: "♡" },
  { href: "/account", label: "Account", icon: "☺" },
];

export function MobileBottomNav() {
  const pathname = usePathname();
  const count = useCartStore((s) => s.cart?.item_count ?? 0);

  return (
    <nav
      aria-label="Mobile"
      className="fixed inset-x-0 bottom-0 z-50 border-t border-[var(--color-border)] bg-[var(--color-surface)]/95 backdrop-blur md:hidden"
      style={{ height: "var(--bottom-nav-h)" }}
    >
      <ul className="grid h-full grid-cols-5">
        {items.map((item) => {
          const active =
            item.href === "/"
              ? pathname === "/"
              : pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <li key={item.href} className="h-full">
              <Link
                href={item.href}
                className={cn(
                  "relative flex h-full flex-col items-center justify-center gap-0.5 text-[11px] font-medium",
                  active ? "text-[var(--color-primary-deep)]" : "text-[var(--color-muted)]",
                )}
              >
                <span
                  className={cn(
                    "text-base leading-none",
                    item.href === "/wishlist" && active && "text-[var(--color-wishlist-active)]",
                  )}
                  aria-hidden
                >
                  {item.href === "/wishlist" && active ? "♥" : item.icon}
                </span>
                {item.label}
                {item.href === "/cart" && count > 0 ? (
                  <span className="absolute right-[22%] top-1.5 rounded-full bg-[var(--color-primary-deep)] px-1.5 text-[10px] text-white">
                    {count}
                  </span>
                ) : null}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
