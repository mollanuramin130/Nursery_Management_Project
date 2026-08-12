"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select } from "@/components/ui/Input";
import { loginHref } from "@/lib/auth-redirect";
import { customerService } from "@/lib/services";
import type { Address } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

type AddressForm = {
  name: string;
  phone: string;
  line1: string;
  line2: string;
  city: string;
  state: string;
  postal_code: string;
  label: string;
  is_default: boolean;
};

const emptyForm = (): AddressForm => ({
  name: "",
  phone: "",
  line1: "",
  line2: "",
  city: "",
  state: "",
  postal_code: "",
  label: "Home",
  is_default: false,
});

function fromAddress(a: Address): AddressForm {
  return {
    name: a.name,
    phone: a.phone,
    line1: a.line1,
    line2: a.line2 ?? "",
    city: a.city,
    state: a.state,
    postal_code: a.postal_code,
    label: a.label ?? "Home",
    is_default: Boolean(a.is_default),
  };
}

export default function AddressesPage() {
  const user = useAuthStore((s) => s.user);
  const bootstrapped = useAuthStore((s) => s.bootstrapped);
  const toast = useToastStore((s) => s.push);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<AddressForm>(emptyForm());

  async function load() {
    const res = await customerService.addresses();
    setAddresses(res.data ?? []);
  }

  useEffect(() => {
    if (!bootstrapped || !user) return;
    let cancelled = false;
    void (async () => {
      try {
        const res = await customerService.addresses();
        if (!cancelled) setAddresses(res.data ?? []);
      } catch (e) {
        if (!cancelled) toast(e instanceof Error ? e.message : "Failed", "error");
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [bootstrapped, user, toast]);

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
        title="Sign in to manage addresses"
        description="Save delivery addresses for faster checkout."
        actionHref={loginHref("/account/addresses")}
        actionLabel="Sign in"
      />
    );
  }

  function openCreate() {
    setEditingId(null);
    setForm({ ...emptyForm(), is_default: addresses.length === 0 });
    setShowForm(true);
  }

  function openEdit(a: Address) {
    setEditingId(a.id);
    setForm(fromAddress(a));
    setShowForm(true);
  }

  function closeForm() {
    setShowForm(false);
    setEditingId(null);
    setForm(emptyForm());
  }

  async function onSave(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    const body = {
      name: form.name.trim(),
      phone: form.phone.trim(),
      line1: form.line1.trim(),
      line2: form.line2.trim() || null,
      city: form.city.trim(),
      state: form.state.trim(),
      postal_code: form.postal_code.trim(),
      label: form.label,
      country: "IN",
      is_default: form.is_default,
    };
    try {
      if (editingId != null) {
        await customerService.updateAddress(editingId, body);
        toast("Address updated");
      } else {
        await customerService.createAddress({
          ...body,
          is_default: form.is_default || addresses.length === 0,
        });
        toast("Address saved");
      }
      closeForm();
      await load();
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not save", "error");
    } finally {
      setSaving(false);
    }
  }

  async function setDefault(id: number) {
    try {
      await customerService.updateAddress(id, { is_default: true });
      toast("Default address updated");
      await load();
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not update default", "error");
    }
  }

  async function onDelete(id: number) {
    if (!window.confirm("Delete this address? This cannot be undone.")) return;
    try {
      await customerService.deleteAddress(id);
      toast("Address removed");
      await load();
    } catch (err) {
      toast(err instanceof Error ? err.message : "Could not delete", "error");
    }
  }

  return (
    <section className="section">
      <div className="container max-w-3xl">
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
          <div className="section-head !mb-0">
            <h1 className="display text-4xl text-[var(--color-primary-deep)]">Addresses</h1>
            <p>Used at checkout for plant delivery. Same data as the Android app.</p>
          </div>
          <Button
            variant="outline"
            onClick={() => (showForm ? closeForm() : openCreate())}
          >
            {showForm ? "Cancel" : "+ Add address"}
          </Button>
        </div>

        {showForm ? (
          <form
            onSubmit={onSave}
            className="mb-6 grid gap-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-5 sm:grid-cols-2"
          >
            <p className="sm:col-span-2 text-sm font-semibold text-[var(--color-primary-deep)]">
              {editingId != null ? "Edit address" : "New address"}
            </p>
            <Field label="Full name">
              <Input
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </Field>
            <Field label="Phone">
              <Input
                required
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </Field>
            <Field label="Address line 1" className="sm:col-span-2">
              <Input
                required
                value={form.line1}
                onChange={(e) => setForm({ ...form, line1: e.target.value })}
              />
            </Field>
            <Field label="Address line 2 (optional)" className="sm:col-span-2">
              <Input
                value={form.line2}
                onChange={(e) => setForm({ ...form, line2: e.target.value })}
              />
            </Field>
            <Field label="City">
              <Input
                required
                value={form.city}
                onChange={(e) => setForm({ ...form, city: e.target.value })}
              />
            </Field>
            <Field label="State">
              <Input
                required
                value={form.state}
                onChange={(e) => setForm({ ...form, state: e.target.value })}
              />
            </Field>
            <Field label="Postal code">
              <Input
                required
                value={form.postal_code}
                onChange={(e) => setForm({ ...form, postal_code: e.target.value })}
              />
            </Field>
            <Field label="Label">
              <Select
                value={form.label}
                onChange={(e) => setForm({ ...form, label: e.target.value })}
              >
                <option>Home</option>
                <option>Office</option>
                <option>Other</option>
              </Select>
            </Field>
            <label className="sm:col-span-2 flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.is_default}
                onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
              />
              Set as default delivery address
            </label>
            <div className="sm:col-span-2">
              <Button type="submit" disabled={saving}>
                {saving ? "Saving…" : editingId != null ? "Update address" : "Save address"}
              </Button>
            </div>
          </form>
        ) : null}

        {!addresses.length && !showForm ? (
          <div className="rounded-[var(--radius-lg)] border border-dashed border-[var(--color-border)] bg-white p-8 text-center">
            <p className="font-semibold text-[var(--color-primary-deep)]">No addresses yet</p>
            <p className="mt-2 text-sm text-[var(--color-muted)]">
              Add a delivery address before your next order.
            </p>
            <Button className="mt-4" onClick={openCreate}>
              Add address
            </Button>
          </div>
        ) : addresses.length ? (
          <ul className="space-y-3">
            {addresses.map((a) => (
              <li
                key={a.id}
                className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">
                      {a.label ?? "Address"} {a.is_default ? "· Default" : ""}
                    </p>
                    <p className="mt-1 text-sm text-[var(--color-ink-soft)]">
                      {a.name} · {a.phone}
                      <br />
                      {a.line1}
                      {a.line2 ? `, ${a.line2}` : ""}
                      <br />
                      {a.city}, {a.state} - {a.postal_code}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-3 text-sm font-semibold">
                    {!a.is_default ? (
                      <button
                        type="button"
                        className="text-[var(--color-primary)]"
                        onClick={() => void setDefault(a.id)}
                      >
                        Make default
                      </button>
                    ) : null}
                    <button
                      type="button"
                      className="text-[var(--color-primary-deep)]"
                      onClick={() => openEdit(a)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="text-[var(--color-error)]"
                      onClick={() => void onDelete(a.id)}
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        ) : null}

        <p className="mt-6 text-sm">
          <Link href="/account" className="font-semibold text-[var(--color-primary)]">
            ← Back to account
          </Link>
        </p>
      </div>
    </section>
  );
}
