"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select } from "@/components/ui/Input";
import {
  createBanner,
  updateBanner,
  type AdminBanner,
  type BannerWritePayload,
} from "@/lib/api/banners";
import { useToastStore } from "@/store/toast";

function toLocal(iso?: string | null) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function BannerForm({
  mode,
  initial,
  bannerId,
}: {
  mode: "create" | "edit";
  initial?: AdminBanner | null;
  bannerId?: number;
}) {
  const router = useRouter();
  const push = useToastStore((s) => s.push);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    title: initial?.title ?? "",
    image_url: initial?.image_url ?? "",
    placement: initial?.placement ?? "home",
    link_type: initial?.link_type ?? "url",
    link_value: initial?.link_value ?? "",
    sort_order: initial?.sort_order != null ? String(initial.sort_order) : "0",
    starts_at: toLocal(initial?.starts_at),
    ends_at: toLocal(initial?.ends_at),
    status: initial?.status ?? "active",
  });

  useEffect(() => {
    if (!initial) return;
    setForm({
      title: initial.title,
      image_url: initial.image_url,
      placement: initial.placement,
      link_type: initial.link_type ?? "url",
      link_value: initial.link_value ?? "",
      sort_order: String(initial.sort_order ?? 0),
      starts_at: toLocal(initial.starts_at),
      ends_at: toLocal(initial.ends_at),
      status: initial.status,
    });
  }, [initial]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: BannerWritePayload = {
        title: form.title.trim(),
        image_url: form.image_url.trim(),
        placement: form.placement.trim(),
        link_type: form.link_type || null,
        link_value: form.link_value.trim() || null,
        sort_order: Number(form.sort_order) || 0,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        status: form.status,
      };
      if (mode === "create") {
        const res = await createBanner(payload);
        push("Banner created", "success");
        router.push(`/banners/${res.data.id}/edit`);
      } else if (bannerId) {
        await updateBanner(bannerId, payload);
        push("Banner updated", "success");
        router.push("/banners");
      }
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="space-y-4" onSubmit={onSubmit}>
      <FormSection title="Banner">
        <div className="grid gap-3 md:grid-cols-2">
          <Input
            label="Title"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            required
          />
          <Input
            label="Placement"
            value={form.placement}
            onChange={(e) => setForm({ ...form, placement: e.target.value })}
            required
            hint="e.g. home, category"
          />
          <Input
            label="Image URL"
            value={form.image_url}
            onChange={(e) => setForm({ ...form, image_url: e.target.value })}
            required
            className="md:col-span-2"
          />
          <Select
            label="Link type"
            value={form.link_type}
            onChange={(e) => setForm({ ...form, link_type: e.target.value })}
          >
            <option value="product">product</option>
            <option value="category">category</option>
            <option value="campaign">campaign</option>
            <option value="url">url</option>
          </Select>
          <Input
            label="Link value"
            value={form.link_value}
            onChange={(e) => setForm({ ...form, link_value: e.target.value })}
            hint="Product/category/campaign id or slug, or full URL"
          />
          <Input
            label="Sort order"
            type="number"
            value={form.sort_order}
            onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
          />
          <Select
            label="Status"
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
          >
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </Select>
          <Input
            label="Starts at"
            type="datetime-local"
            value={form.starts_at}
            onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
          />
          <Input
            label="Ends at"
            type="datetime-local"
            value={form.ends_at}
            onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
          />
        </div>
        {form.image_url ? (
          <div className="mt-4">
            <p className="mb-2 text-xs font-medium text-[var(--admin-muted)]">Preview</p>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={form.image_url}
              alt=""
              className="max-h-40 max-w-full rounded-[var(--admin-radius)] border border-[var(--admin-border)] object-contain"
            />
          </div>
        ) : null}
      </FormSection>
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? "Saving…" : mode === "create" ? "Create" : "Save"}
        </Button>
        <Button type="button" variant="secondary" onClick={() => router.push("/banners")}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
