"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import { Skeleton } from "@/components/ui/Skeleton";
import { notificationHref } from "@/lib/notification-deep-link";
import { cn } from "@/lib/cn";

type Notification = {
  id: number;
  type: string;
  title: string;
  body?: string | null;
  data?: Record<string, unknown> | null;
  is_read: boolean;
  created_at?: string | null;
};

function hrefFor(n: Notification): string | null {
  return notificationHref(n.data, "customer");
}

export default function NotificationsPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [unread, setUnread] = useState(0);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiGet<Notification[]>("/notifications", { per_page: 30 });
      setItems(Array.isArray(res.data) ? res.data : []);
      const meta = res.meta as { unread_count?: number } | undefined;
      setUnread(meta?.unread_count ?? 0);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not load notifications");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (bootstrapped && user) void load();
  }, [bootstrapped, user, load]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-2xl space-y-3">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-24 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Notifications"
        description="Sign in to see order and offer updates."
        actionHref={loginHref("/account/notifications")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-2xl">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div className="section-head mb-0">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">Notifications</h1>
            <p>{unread > 0 ? `${unread} unread` : "You’re all caught up."}</p>
          </div>
          {unread > 0 ? (
            <Button
              variant="outline"
              size="sm"
              onClick={async () => {
                await apiSend("post", "/notifications/read-all");
                await load();
              }}
            >
              Mark all read
            </Button>
          ) : null}
        </div>

        {loading ? (
          <div className="mt-8 space-y-3">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        ) : error ? (
          <p className="mt-8 text-[var(--color-muted)]">{error}</p>
        ) : !items.length ? (
          <p className="mt-8 text-[var(--color-muted)]">You’re all caught up.</p>
        ) : (
          <ul className="mt-8 space-y-2">
            {items.map((n) => {
              const href = hrefFor(n);
              const inner = (
                <>
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-semibold text-[var(--color-primary-deep)]">{n.title}</p>
                    {!n.is_read ? (
                      <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--color-primary)]" aria-label="Unread" />
                    ) : null}
                  </div>
                  {n.body ? (
                    <p className="mt-1 text-sm text-[var(--color-ink-soft)]">{n.body}</p>
                  ) : null}
                  {n.created_at ? (
                    <p className="mt-2 text-xs text-[var(--color-muted)]">
                      {new Date(n.created_at).toLocaleString("en-IN")}
                    </p>
                  ) : null}
                </>
              );

              return (
                <li key={n.id}>
                  {href ? (
                    <Link
                      href={href}
                      className={cn(
                        "block rounded-[var(--radius-lg)] border border-[var(--color-border)] p-4 hover:border-[var(--color-primary)]",
                        !n.is_read && "bg-[var(--color-primary-soft)]/40",
                      )}
                      onClick={() => {
                        if (!n.is_read) void apiSend("post", `/notifications/${n.id}/read`);
                      }}
                    >
                      {inner}
                    </Link>
                  ) : (
                    <button
                      type="button"
                      className={cn(
                        "w-full rounded-[var(--radius-lg)] border border-[var(--color-border)] p-4 text-left hover:border-[var(--color-primary)]",
                        !n.is_read && "bg-[var(--color-primary-soft)]/40",
                      )}
                      onClick={async () => {
                        if (!n.is_read) {
                          await apiSend("post", `/notifications/${n.id}/read`);
                          await load();
                        }
                      }}
                    >
                      {inner}
                    </button>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </section>
  );
}
