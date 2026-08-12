"use client";

import { useEffect, useState } from "react";
import { apiGet, apiSend } from "@/lib/api";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import { Skeleton } from "@/components/ui/Skeleton";

type Prefs = {
  marketing_opt_in: boolean;
  preferred_language: string;
  notify_orders: boolean;
  notify_promotions: boolean;
  notify_email: boolean;
  notify_push: boolean;
};

function Toggle({
  label,
  description,
  checked,
  onChange,
}: {
  label: string;
  description: string;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <label className="flex cursor-pointer items-start justify-between gap-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4">
      <span>
        <span className="block font-semibold">{label}</span>
        <span className="mt-1 block text-sm text-[var(--color-muted)]">{description}</span>
      </span>
      <input
        type="checkbox"
        className="mt-1 h-5 w-5"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
      />
    </label>
  );
}

export default function PreferencesPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const toast = useToastStore((s) => s.push);
  const [prefs, setPrefs] = useState<Prefs | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!bootstrapped || !user) return;
    void apiGet<Prefs>("/customer/preferences")
      .then((res) => setPrefs(res.data))
      .catch((e) => toast(e instanceof Error ? e.message : "Failed to load", "error"));
  }, [bootstrapped, user, toast]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container max-w-xl space-y-3">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-24 w-full" />
        </div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Preferences"
        description="Sign in to manage notification preferences."
        actionHref={loginHref("/account/preferences")}
        actionLabel="Sign in"
      />
    );
  }

  return (
    <section className="section">
      <div className="container max-w-xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Preferences</h1>
          <p>Choose how GreenLeaf keeps you updated.</p>
        </div>

        {!prefs ? (
          <Skeleton className="h-40 w-full" />
        ) : (
          <div className="space-y-3">
            <Toggle
              label="Order updates"
              description="Shipping, delivery, returns, and refunds."
              checked={prefs.notify_orders}
              onChange={(v) => setPrefs({ ...prefs, notify_orders: v })}
            />
            <Toggle
              label="Promotions"
              description="Campaigns and seasonal offers."
              checked={prefs.notify_promotions}
              onChange={(v) => setPrefs({ ...prefs, notify_promotions: v })}
            />
            <Toggle
              label="Marketing emails"
              description="Occasional nursery tips and newsletters."
              checked={prefs.marketing_opt_in}
              onChange={(v) => setPrefs({ ...prefs, marketing_opt_in: v })}
            />
            <Toggle
              label="Email channel"
              description="Allow email for the toggles above."
              checked={prefs.notify_email}
              onChange={(v) => setPrefs({ ...prefs, notify_email: v })}
            />
            <Toggle
              label="Push channel"
              description="Used when mobile push is enabled later."
              checked={prefs.notify_push}
              onChange={(v) => setPrefs({ ...prefs, notify_push: v })}
            />
            <Button
              disabled={saving}
              onClick={async () => {
                setSaving(true);
                try {
                  const res = await apiSend<Prefs>("put", "/customer/preferences", prefs);
                  setPrefs(res.data);
                  toast("Preferences saved");
                } catch (e) {
                  toast(e instanceof Error ? e.message : "Save failed", "error");
                } finally {
                  setSaving(false);
                }
              }}
            >
              {saving ? "Saving…" : "Save preferences"}
            </Button>
          </div>
        )}
      </div>
    </section>
  );
}
