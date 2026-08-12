"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input } from "@/components/ui/Input";
import { loginHref } from "@/lib/auth-redirect";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function ProfilePage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const updateProfile = useAuthStore((s) => s.updateProfile);
  const loading = useAuthStore((s) => s.loading);
  const toast = useToastStore((s) => s.push);
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");

  useEffect(() => {
    if (!user) return;
    setName(user.name ?? "");
    setPhone(user.phone ?? "");
  }, [user]);

  if (!bootstrapped) {
    return (
      <section className="section">
        <div className="container text-[var(--color-muted)]">Loading…</div>
      </section>
    );
  }

  if (!user) {
    return (
      <EmptyState
        title="Sign in to edit your profile"
        description="Update your name and phone used for deliveries."
        actionHref={loginHref("/account/profile")}
        actionLabel="Sign in"
      />
    );
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    try {
      await updateProfile({
        name: name.trim(),
        phone: phone.trim() ? phone.trim() : null,
      });
      toast("Profile updated");
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not update profile", "error");
    }
  }

  return (
    <section className="section">
      <div className="container max-w-xl">
        <div className="section-head">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">Profile</h1>
          <p>Email cannot be changed here. Contact support if you need a new email.</p>
        </div>
        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-6"
        >
          <Field label="Full name" htmlFor="name">
            <Input
              id="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              maxLength={120}
            />
          </Field>
          <Field label="Email">
            <Input value={user.email} disabled readOnly />
          </Field>
          <Field label="Phone" htmlFor="phone">
            <Input
              id="phone"
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              maxLength={20}
            />
          </Field>
          <Button type="submit" disabled={loading}>
            {loading ? "Saving…" : "Save changes"}
          </Button>
        </form>
        <p className="mt-6 text-sm">
          <Link href="/account" className="font-semibold text-[var(--color-primary)]">
            ← Back to account
          </Link>
        </p>
      </div>
    </section>
  );
}
