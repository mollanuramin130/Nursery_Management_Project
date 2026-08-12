"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchCategories } from "@/lib/api/categories";
import {
  addProductImages,
  createProduct,
  updateProduct,
  type ProductWritePayload,
} from "@/lib/api/products";
import type { AdminCategory, AdminProductDetail } from "@/lib/types";
import { useToastStore } from "@/store/toast";

type FormState = {
  name: string;
  sku: string;
  slug: string;
  product_type: string;
  price: string;
  compare_at_price: string;
  status: string;
  description: string;
  tags: string;
  category_ids: number[];
  is_featured: boolean;
  is_new: boolean;
  qty_on_hand: string;
  low_stock_threshold: string;
  warehouse_id: string;
  common_name: string;
  scientific_name: string;
  indoor_outdoor: string;
  sunlight: string;
  water_requirement: string;
  difficulty_level: string;
  pet_safety: string;
  image_url: string;
  image_alt: string;
  image_primary: boolean;
};

function emptyForm(): FormState {
  return {
    name: "",
    sku: "",
    slug: "",
    product_type: "plant",
    price: "",
    compare_at_price: "",
    status: "draft",
    description: "",
    tags: "",
    category_ids: [],
    is_featured: false,
    is_new: false,
    qty_on_hand: "0",
    low_stock_threshold: "5",
    warehouse_id: String(process.env.NEXT_PUBLIC_DEFAULT_WAREHOUSE_ID ?? "1"),
    common_name: "",
    scientific_name: "",
    indoor_outdoor: "",
    sunlight: "",
    water_requirement: "",
    difficulty_level: "",
    pet_safety: "",
    image_url: "",
    image_alt: "",
    image_primary: true,
  };
}

function fromProduct(product: AdminProductDetail): FormState {
  const plant = (product.plant ?? {}) as Record<string, unknown>;
  const inv = product.inventory?.[0];
  return {
    ...emptyForm(),
    name: product.name,
    sku: product.sku,
    slug: product.slug,
    product_type: product.product_type,
    price: String(product.price ?? ""),
    compare_at_price:
      product.compare_at_price != null ? String(product.compare_at_price) : "",
    status: product.status,
    description: product.description ?? "",
    tags: (product.tags ?? []).join(", "),
    category_ids: product.category_ids ?? [],
    is_featured: Boolean(product.is_featured),
    is_new: Boolean(product.is_new),
    qty_on_hand: inv ? String(inv.qty_on_hand) : "0",
    low_stock_threshold: inv ? String(inv.low_stock_threshold) : "5",
    warehouse_id: inv
      ? String(inv.warehouse_id)
      : String(process.env.NEXT_PUBLIC_DEFAULT_WAREHOUSE_ID ?? "1"),
    common_name: String(plant.common_name ?? ""),
    scientific_name: String(plant.scientific_name ?? ""),
    indoor_outdoor: String(plant.indoor_outdoor ?? ""),
    sunlight: String(plant.sunlight ?? ""),
    water_requirement: String(plant.water_requirement ?? ""),
    difficulty_level: String(plant.difficulty_level ?? ""),
    pet_safety: String(plant.pet_safety ?? ""),
  };
}

export function ProductForm({
  mode,
  productId,
  initial,
}: {
  mode: "create" | "edit";
  productId?: number;
  initial?: AdminProductDetail | null;
}) {
  const router = useRouter();
  const push = useToastStore((s) => s.push);
  const [form, setForm] = useState<FormState>(() =>
    initial ? fromProduct(initial) : emptyForm(),
  );
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [saving, setSaving] = useState(false);
  const [categoryError, setCategoryError] = useState<string | null>(null);

  useEffect(() => {
    if (initial) setForm(fromProduct(initial));
  }, [initial]);

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetchCategories();
        setCategories(res.data);
      } catch (err) {
        setCategoryError(
          err instanceof ApiError
            ? `${err.message} (categories require products.write)`
            : "Could not load categories",
        );
      }
    })();
  }, []);

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  function toggleCategory(id: number) {
    setForm((prev) => ({
      ...prev,
      category_ids: prev.category_ids.includes(id)
        ? prev.category_ids.filter((c) => c !== id)
        : [...prev.category_ids, id],
    }));
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const tags = form.tags
        .split(",")
        .map((t) => t.trim())
        .filter(Boolean);

      const payload: ProductWritePayload = {
        name: form.name.trim(),
        sku: form.sku.trim(),
        slug: form.slug.trim() || undefined,
        product_type: form.product_type,
        price: Number(form.price),
        compare_at_price: form.compare_at_price ? Number(form.compare_at_price) : null,
        status: form.status,
        description: form.description || null,
        category_ids: form.category_ids,
        tags,
        is_featured: form.is_featured,
        is_new: form.is_new,
      };

      if (form.product_type === "plant") {
        payload.plant = {
          common_name: form.common_name || form.name,
          scientific_name: form.scientific_name || null,
          indoor_outdoor: form.indoor_outdoor || null,
          sunlight: form.sunlight || null,
          water_requirement: form.water_requirement || null,
          difficulty_level: form.difficulty_level || null,
          pet_safety: form.pet_safety || null,
        };
      }

      let id = productId;
      if (mode === "create") {
        payload.inventory = {
          warehouse_id: Number(form.warehouse_id),
          qty_on_hand: Number(form.qty_on_hand || 0),
          low_stock_threshold: Number(form.low_stock_threshold || 5),
        };
        const created = await createProduct(payload);
        id = created.data.id;
        push("Product created", "success");
      } else if (id) {
        await updateProduct(id, payload);
        push("Product updated", "success");
      }

      if (id && form.image_url.trim()) {
        await addProductImages(id, [
          {
            url: form.image_url.trim(),
            alt: form.image_alt || form.name,
            is_primary: form.image_primary,
            sort_order: 0,
          },
        ]);
      }

      router.push(id ? `/products/${id}/edit` : "/products");
      router.refresh();
    } catch (err) {
      push(err instanceof Error ? err.message : "Save failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">Basic information</h2>
        <div className="grid gap-3 md:grid-cols-2">
          <Input label="Name" value={form.name} onChange={(e) => set("name", e.target.value)} required />
          <Input label="SKU" value={form.sku} onChange={(e) => set("sku", e.target.value)} required />
          <Input label="Slug" value={form.slug} onChange={(e) => set("slug", e.target.value)} hint="Optional — auto-generated if empty" />
          <Select
            label="Product type"
            value={form.product_type}
            onChange={(e) => set("product_type", e.target.value)}
          >
            <option value="plant">plant</option>
            <option value="pot">pot</option>
            <option value="soil">soil</option>
            <option value="tool">tool</option>
            <option value="accessory">accessory</option>
            <option value="gift">gift</option>
          </Select>
          <Select
            label="Status"
            value={form.status}
            onChange={(e) => set("status", e.target.value)}
          >
            <option value="draft">draft</option>
            <option value="active">active</option>
            <option value="archived">archived</option>
          </Select>
          <Input
            label="Tags"
            value={form.tags}
            onChange={(e) => set("tags", e.target.value)}
            hint="Comma-separated"
          />
          <label className="flex items-center gap-2 text-sm md:col-span-2">
            <input
              type="checkbox"
              checked={form.is_featured}
              onChange={(e) => set("is_featured", e.target.checked)}
            />
            Featured
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.is_new}
              onChange={(e) => set("is_new", e.target.checked)}
            />
            New arrival
          </label>
        </div>
      </section>

      <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">Pricing</h2>
        <div className="grid gap-3 md:grid-cols-2">
          <Input
            label="Price"
            type="number"
            min="0"
            step="0.01"
            value={form.price}
            onChange={(e) => set("price", e.target.value)}
            required
          />
          <Input
            label="Compare-at price"
            type="number"
            min="0"
            step="0.01"
            value={form.compare_at_price}
            onChange={(e) => set("compare_at_price", e.target.value)}
          />
        </div>
      </section>

      <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">Categories</h2>
        {categoryError ? (
          <p className="text-sm text-[var(--admin-warning)]">{categoryError}</p>
        ) : (
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((cat) => (
              <label key={cat.id} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.category_ids.includes(cat.id)}
                  onChange={() => toggleCategory(cat.id)}
                />
                {cat.name}
              </label>
            ))}
          </div>
        )}
      </section>

      {mode === "create" ? (
        <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">Inventory</h2>
          <div className="grid gap-3 md:grid-cols-3">
            <Input
              label="Warehouse ID"
              type="number"
              value={form.warehouse_id}
              onChange={(e) => set("warehouse_id", e.target.value)}
              hint="No warehouses list API — uses default warehouse id"
            />
            <Input
              label="Qty on hand"
              type="number"
              min="0"
              value={form.qty_on_hand}
              onChange={(e) => set("qty_on_hand", e.target.value)}
            />
            <Input
              label="Low stock threshold"
              type="number"
              min="0"
              value={form.low_stock_threshold}
              onChange={(e) => set("low_stock_threshold", e.target.value)}
            />
          </div>
          <p className="mt-2 text-xs text-[var(--admin-muted)]">
            Inventory adjustments after create are handled in a later Inventory phase.
          </p>
        </section>
      ) : initial?.inventory?.length ? (
        <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">Inventory (read-only)</h2>
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-2 py-2">Warehouse</th>
                  <th className="px-2 py-2">On hand</th>
                  <th className="px-2 py-2">Sellable</th>
                  <th className="px-2 py-2">Threshold</th>
                </tr>
              </thead>
              <tbody>
                {initial.inventory.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-2 py-2">{row.warehouse_code ?? row.warehouse_id}</td>
                    <td className="px-2 py-2">{row.qty_on_hand}</td>
                    <td className="px-2 py-2">{row.sellable}</td>
                    <td className="px-2 py-2">{row.low_stock_threshold}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}

      {form.product_type === "plant" ? (
        <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">Plant information</h2>
          <div className="grid gap-3 md:grid-cols-2">
            <Input label="Common name" value={form.common_name} onChange={(e) => set("common_name", e.target.value)} />
            <Input label="Scientific name" value={form.scientific_name} onChange={(e) => set("scientific_name", e.target.value)} />
            <Input label="Indoor / outdoor" value={form.indoor_outdoor} onChange={(e) => set("indoor_outdoor", e.target.value)} />
            <Input label="Sunlight" value={form.sunlight} onChange={(e) => set("sunlight", e.target.value)} />
            <Input label="Water requirement" value={form.water_requirement} onChange={(e) => set("water_requirement", e.target.value)} />
            <Input label="Difficulty" value={form.difficulty_level} onChange={(e) => set("difficulty_level", e.target.value)} />
            <Input label="Pet safety" value={form.pet_safety} onChange={(e) => set("pet_safety", e.target.value)} />
          </div>
        </section>
      ) : null}

      <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">Description</h2>
        <TextArea
          label="Description"
          value={form.description}
          onChange={(e) => set("description", e.target.value)}
        />
      </section>

      <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">Images</h2>
        <p className="mb-3 text-xs text-[var(--admin-muted)]">
          Admin API accepts image URLs (no direct browser upload endpoint). Provide a hosted URL.
        </p>
        {initial?.images?.length ? (
          <ul className="mb-3 space-y-1 text-sm">
            {initial.images.map((img) => (
              <li key={img.id} className="truncate">
                {img.is_primary ? "★ " : ""}
                <a href={img.url} className="text-[var(--admin-primary)] hover:underline" target="_blank" rel="noreferrer">
                  {img.url}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
        <div className="grid gap-3 md:grid-cols-2">
          <Input
            label="Add image URL"
            value={form.image_url}
            onChange={(e) => set("image_url", e.target.value)}
            placeholder="https://…"
          />
          <Input
            label="Alt text"
            value={form.image_alt}
            onChange={(e) => set("image_alt", e.target.value)}
          />
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.image_primary}
              onChange={(e) => set("image_primary", e.target.checked)}
            />
            Set as primary
          </label>
        </div>
      </section>

      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? "Saving…" : mode === "create" ? "Create product" : "Save changes"}
        </Button>
        <Button type="button" variant="secondary" onClick={() => router.push("/products")}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
