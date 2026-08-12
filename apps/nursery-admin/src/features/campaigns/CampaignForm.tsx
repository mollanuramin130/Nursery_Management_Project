"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import {
  createCampaign,
  updateCampaign,
  type AdminCampaign,
  type CampaignWritePayload,
} from "@/lib/api/campaigns";
import { fetchProducts } from "@/lib/api/products";
import type { AdminProductListItem } from "@/lib/types";
import { useToastStore } from "@/store/toast";

function toLocal(iso?: string | null) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function CampaignForm({
  mode,
  initial,
  campaignId,
}: {
  mode: "create" | "edit";
  initial?: AdminCampaign | null;
  campaignId?: number;
}) {
  const router = useRouter();
  const push = useToastStore((s) => s.push);
  const [saving, setSaving] = useState(false);
  const [products, setProducts] = useState<AdminProductListItem[]>([]);
  const [form, setForm] = useState({
    title: initial?.title ?? "",
    slug: initial?.slug ?? "",
    subtitle: initial?.subtitle ?? "",
    description: initial?.description ?? "",
    type: initial?.type ?? "seasonal",
    season_code: initial?.season_code ?? "",
    image_url: initial?.image_url ?? "",
    starts_at: toLocal(initial?.starts_at),
    ends_at: toLocal(initial?.ends_at),
    status: initial?.status ?? "draft",
    priority: initial?.priority != null ? String(initial.priority) : "0",
    product_ids: initial?.product_ids ?? ([] as number[]),
  });

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetchProducts({ per_page: 50, page: 1 });
        setProducts(res.data);
      } catch {
        setProducts([]);
      }
    })();
  }, []);

  useEffect(() => {
    if (!initial) return;
    setForm({
      title: initial.title,
      slug: initial.slug,
      subtitle: initial.subtitle ?? "",
      description: initial.description ?? "",
      type: initial.type ?? "seasonal",
      season_code: initial.season_code ?? "",
      image_url: initial.image_url ?? "",
      starts_at: toLocal(initial.starts_at),
      ends_at: toLocal(initial.ends_at),
      status: initial.status,
      priority: initial.priority != null ? String(initial.priority) : "0",
      product_ids: initial.product_ids ?? [],
    });
  }, [initial]);

  function toggleProduct(id: number) {
    setForm((prev) => ({
      ...prev,
      product_ids: prev.product_ids.includes(id)
        ? prev.product_ids.filter((x) => x !== id)
        : [...prev.product_ids, id],
    }));
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!form.starts_at || !form.ends_at) {
      push("Starts at and ends at are required", "error");
      return;
    }
    setSaving(true);
    try {
      const payload: CampaignWritePayload = {
        title: form.title.trim(),
        slug: form.slug.trim() || undefined,
        subtitle: form.subtitle.trim() || null,
        description: form.description.trim() || null,
        type: form.type || undefined,
        season_code: form.season_code.trim() || null,
        image_url: form.image_url.trim() || null,
        starts_at: form.starts_at,
        ends_at: form.ends_at,
        status: form.status,
        priority: Number(form.priority) || 0,
        product_ids: form.product_ids,
      };
      if (mode === "create") {
        const res = await createCampaign(payload);
        push("Campaign created", "success");
        router.push(`/campaigns/${res.data.id}/edit`);
      } else if (campaignId) {
        await updateCampaign(campaignId, payload);
        push("Campaign updated", "success");
        router.push("/campaigns");
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
          <Input
            label="Title"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            required
          />
          <Input
            label="Slug"
            value={form.slug}
            onChange={(e) => setForm({ ...form, slug: e.target.value })}
            hint="Leave blank to auto-generate on create"
          />
          <Input
            label="Subtitle"
            value={form.subtitle}
            onChange={(e) => setForm({ ...form, subtitle: e.target.value })}
          />
          <Select
            label="Type"
            value={form.type}
            onChange={(e) => setForm({ ...form, type: e.target.value })}
          >
            <option value="seasonal">seasonal</option>
            <option value="festival">festival</option>
            <option value="flash">flash</option>
            <option value="collection">collection</option>
          </Select>
          <Input
            label="Season code"
            value={form.season_code}
            onChange={(e) => setForm({ ...form, season_code: e.target.value })}
            hint="e.g. monsoon, diwali"
          />
          <Select
            label="Status"
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
          >
            <option value="draft">draft</option>
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </Select>
          <Input
            label="Priority"
            type="number"
            value={form.priority}
            onChange={(e) => setForm({ ...form, priority: e.target.value })}
          />
          <Input
            label="Image URL"
            value={form.image_url}
            onChange={(e) => setForm({ ...form, image_url: e.target.value })}
          />
        </div>
        <div className="mt-3">
          <TextArea
            label="Description"
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
          />
        </div>
      </FormSection>

      <FormSection title="Schedule">
        <div className="grid gap-3 md:grid-cols-2">
          <Input
            label="Starts at"
            type="datetime-local"
            value={form.starts_at}
            onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
            required
          />
          <Input
            label="Ends at"
            type="datetime-local"
            value={form.ends_at}
            onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
            required
          />
        </div>
      </FormSection>

      <FormSection
        title="Products"
        description={`${form.product_ids.length} selected · loaded from products (per_page=50)`}
      >
        {products.length === 0 ? (
          <p className="text-sm text-[var(--admin-muted)]">No products loaded.</p>
        ) : (
          <div className="max-h-64 space-y-1 overflow-y-auto rounded-[var(--admin-radius)] border border-[var(--admin-border)] p-2">
            {products.map((p) => (
              <label key={p.id} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.product_ids.includes(p.id)}
                  onChange={() => toggleProduct(p.id)}
                />
                <span className="font-medium">{p.name}</span>
                <span className="font-mono text-xs text-[var(--admin-muted)]">{p.sku}</span>
              </label>
            ))}
          </div>
        )}
      </FormSection>

      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? "Saving…" : mode === "create" ? "Create" : "Save"}
        </Button>
        <Button type="button" variant="secondary" onClick={() => router.push("/campaigns")}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
