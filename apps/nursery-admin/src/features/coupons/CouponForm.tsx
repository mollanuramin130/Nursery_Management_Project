"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import {
  createCoupon,
  updateCoupon,
  type AdminCoupon,
  type CouponWritePayload,
} from "@/lib/api/coupons";
import { useToastStore } from "@/store/toast";

function toLocal(iso?: string | null) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function CouponForm({
  mode,
  initial,
  couponId,
}: {
  mode: "create" | "edit";
  initial?: AdminCoupon | null;
  couponId?: number;
}) {
  const router = useRouter();
  const push = useToastStore((s) => s.push);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    code: initial?.code ?? "",
    name: initial?.name ?? "",
    discount_type: initial?.discount_type ?? "percent",
    discount_value: initial ? String(initial.discount_value) : "",
    min_order_amount: initial?.min_order_amount != null ? String(initial.min_order_amount) : "",
    max_discount_amount:
      initial?.max_discount_amount != null ? String(initial.max_discount_amount) : "",
    usage_limit_total: initial?.usage_limit_total != null ? String(initial.usage_limit_total) : "",
    usage_limit_per_user:
      initial?.usage_limit_per_user != null ? String(initial.usage_limit_per_user) : "",
    starts_at: toLocal(initial?.starts_at),
    ends_at: toLocal(initial?.ends_at),
    status: initial?.status ?? "active",
    is_public: initial?.is_public ?? true,
    stackable: initial?.stackable ?? false,
  });

  useEffect(() => {
    if (!initial) return;
    setForm({
      code: initial.code,
      name: initial.name,
      discount_type: initial.discount_type,
      discount_value: String(initial.discount_value),
      min_order_amount: initial.min_order_amount != null ? String(initial.min_order_amount) : "",
      max_discount_amount:
        initial.max_discount_amount != null ? String(initial.max_discount_amount) : "",
      usage_limit_total: initial.usage_limit_total != null ? String(initial.usage_limit_total) : "",
      usage_limit_per_user:
        initial.usage_limit_per_user != null ? String(initial.usage_limit_per_user) : "",
      starts_at: toLocal(initial.starts_at),
      ends_at: toLocal(initial.ends_at),
      status: initial.status,
      is_public: initial.is_public ?? true,
      stackable: initial.stackable ?? false,
    });
  }, [initial]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: CouponWritePayload = {
        code: form.code.trim().toUpperCase(),
        name: form.name.trim(),
        discount_type: form.discount_type,
        discount_value: Number(form.discount_value),
        min_order_amount: form.min_order_amount ? Number(form.min_order_amount) : null,
        max_discount_amount: form.max_discount_amount ? Number(form.max_discount_amount) : null,
        usage_limit_total: form.usage_limit_total ? Number(form.usage_limit_total) : null,
        usage_limit_per_user: form.usage_limit_per_user ? Number(form.usage_limit_per_user) : null,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        status: form.status,
        is_public: form.is_public,
        stackable: form.stackable,
      };
      if (mode === "create") {
        const res = await createCoupon(payload);
        push("Coupon created", "success");
        router.push(`/coupons/${res.data.id}/edit`);
      } else if (couponId) {
        await updateCoupon(couponId, payload);
        push("Coupon updated", "success");
        router.push("/coupons");
      }
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="space-y-4" onSubmit={onSubmit}>
      <FormSection title="Basic information">
        <div className="grid gap-3 md:grid-cols-2">
          <Input label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required />
          <Input label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          <Select label="Status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </Select>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.is_public} onChange={(e) => setForm({ ...form, is_public: e.target.checked })} />
            Public coupon
          </label>
        </div>
      </FormSection>
      <FormSection title="Discount">
        <div className="grid gap-3 md:grid-cols-3">
          <Select label="Type" value={form.discount_type} onChange={(e) => setForm({ ...form, discount_type: e.target.value })}>
            <option value="percent">percent</option>
            <option value="fixed">fixed</option>
          </Select>
          <Input label="Value" type="number" min="0" step="0.01" value={form.discount_value} onChange={(e) => setForm({ ...form, discount_value: e.target.value })} required />
          <Input label="Max discount" type="number" min="0" step="0.01" value={form.max_discount_amount} onChange={(e) => setForm({ ...form, max_discount_amount: e.target.value })} />
        </div>
      </FormSection>
      <FormSection title="Conditions">
        <div className="grid gap-3 md:grid-cols-2">
          <Input label="Minimum order" type="number" min="0" step="0.01" value={form.min_order_amount} onChange={(e) => setForm({ ...form, min_order_amount: e.target.value })} />
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.stackable} onChange={(e) => setForm({ ...form, stackable: e.target.checked })} />
            Stackable
          </label>
        </div>
        <p className="mt-2 text-xs text-[var(--admin-muted)]">Product/category/customer restrictions are not in the coupon API.</p>
      </FormSection>
      <FormSection title="Validity & usage">
        <div className="grid gap-3 md:grid-cols-2">
          <Input label="Starts at" type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
          <Input label="Ends at" type="datetime-local" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
          <Input label="Total usage limit" type="number" min="1" value={form.usage_limit_total} onChange={(e) => setForm({ ...form, usage_limit_total: e.target.value })} />
          <Input label="Per-customer limit" type="number" min="1" value={form.usage_limit_per_user} onChange={(e) => setForm({ ...form, usage_limit_per_user: e.target.value })} />
        </div>
      </FormSection>
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>{saving ? "Saving…" : mode === "create" ? "Create" : "Save"}</Button>
        <Button type="button" variant="secondary" onClick={() => router.push("/coupons")}>Cancel</Button>
      </div>
    </form>
  );
}
