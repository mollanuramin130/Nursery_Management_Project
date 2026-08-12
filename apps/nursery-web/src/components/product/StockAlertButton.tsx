"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export function StockAlertButton({
  productId,
  productSlug,
}: {
  productId: number;
  productSlug: string;
}) {
  const user = useAuthStore((s) => s.user);
  const toast = useToastStore((s) => s.push);
  const router = useRouter();
  const [subscribed, setSubscribed] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    void apiGet<{ subscribed: boolean }>(`/products/${productId}/stock-alert`)
      .then((res) => setSubscribed(Boolean(res.data?.subscribed)))
      .catch(() => undefined);
  }, [user, productId]);

  async function toggle() {
    if (!user) {
      router.push(loginHref(`/product/${productSlug}`));
      return;
    }
    setBusy(true);
    try {
      if (subscribed) {
        await apiSend("delete", `/products/${productId}/stock-alert`);
        setSubscribed(false);
        toast("Stock alert cancelled", "info");
      } else {
        await apiSend("post", `/products/${productId}/stock-alert`);
        setSubscribed(true);
        toast("We'll notify you when this is back in stock");
      }
    } catch (e) {
      toast(e instanceof Error ? e.message : "Could not update alert", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface-muted)] p-4">
      <p className="text-sm font-semibold text-[var(--color-primary-deep)]">Out of stock</p>
      <p className="mt-1 text-sm text-[var(--color-muted)]">
        Get an in-app notification when this plant is available again.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <Button size="sm" disabled={busy} onClick={() => void toggle()}>
          {busy ? "…" : subscribed ? "Cancel alert" : "Notify me"}
        </Button>
        <Link
          href="/find-your-plant"
          className="inline-flex min-h-10 items-center rounded-full border border-[var(--color-border)] px-3 text-sm font-semibold"
        >
          Find similar
        </Link>
      </div>
    </div>
  );
}
