"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { useCartStore } from "@/store/cart";
import { useWishlistStore } from "@/store/wishlist";

export default function AccountPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const logout = useAuthStore((s) => s.logout);
  const fetchCart = useCartStore((s) => s.fetchCart);
  const clearWish = useWishlistStore((s) => s.clearLocal);
  const router = useRouter();

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container text-[var(--color-muted)]">Restoring session…</div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Your account"
        description="Sign in to manage orders, addresses, and wishlist."
        actionHref={loginHref("/account")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container grid gap-8 md:grid-cols-[240px_1fr]">
        <aside className="h-fit rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
          <p className="px-2 text-xs font-bold uppercase tracking-wide text-[var(--color-muted)]">
            My account
          </p>
          <nav className="mt-2 space-y-1">
            {[
              { href: "/account", label: "Overview" },
              { href: "/account/profile", label: "Profile" },
              { href: "/account/orders", label: "Orders" },
              { href: "/account/returns", label: "Returns" },
              { href: "/account/reviews", label: "My reviews" },
              { href: "/account/rewards", label: "Rewards" },
              { href: "/account/subscriptions", label: "Subscriptions" },
              { href: "/account/addresses", label: "Addresses" },
              { href: "/wishlist", label: "Wishlist" },
              { href: "/account/notifications", label: "Notifications" },
              { href: "/account/preferences", label: "Preferences" },
              { href: "/offers", label: "Offers" },
              { href: "/cart", label: "Cart" },
            ].map((l) => (
              <Link
                key={l.href}
                href={l.href}
                className="block rounded-[var(--radius-md)] px-3 py-2.5 text-sm font-medium hover:bg-[var(--color-primary-soft)]"
              >
                {l.label}
              </Link>
            ))}
          </nav>
        </aside>

        <div>
          <div className="section-head">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">
              Hello, {user.name.split(" ")[0]}
            </h1>
            <p>{user.email}</p>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Link
              href="/account/profile"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Profile</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">
                {user.phone ? user.phone : "Add a phone number"}
              </p>
            </Link>
            <Link
              href="/account/addresses"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Addresses</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Delivery locations for checkout</p>
            </Link>
            <Link
              href="/account/orders"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Orders</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Track, reorder, and return</p>
            </Link>
            <Link
              href="/account/returns"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Returns</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Return request status</p>
            </Link>
            <Link
              href="/account/reviews"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">My reviews</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Submitted ratings and status</p>
            </Link>
            <Link
              href="/account/rewards"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Rewards</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Points balance and history</p>
            </Link>
            <Link
              href="/account/subscriptions"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Subscriptions</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Recurring kits — pay each cycle</p>
            </Link>
            <Link
              href="/wishlist"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Wishlist</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Plants you’ve saved</p>
            </Link>
            <Link
              href="/account/notifications"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Notifications</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Order and offer updates</p>
            </Link>
            <Link
              href="/account/preferences"
              className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 hover:border-[var(--color-primary)]"
            >
              <h2 className="font-semibold">Preferences</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">Email and alert settings</p>
            </Link>
          </div>
          <div className="mt-6">
            <Button
              variant="outline"
              onClick={async () => {
                await logout();
                clearWish();
                await fetchCart();
                router.push("/");
              }}
            >
              Log out
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
}
