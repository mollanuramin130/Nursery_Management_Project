"use client";

import { useCallback, useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { deleteProduct, fetchProducts } from "@/lib/api/products";
import { hasPermission } from "@/lib/auth/permissions";
import { formatMoney } from "@/lib/format";
import type { AdminProductListItem, Pagination } from "@/lib/types";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "draft") return "warning" as const;
  return "neutral" as const;
}

export default function ProductsPage() {
  const user = useAuthStore((s) => s.user);
  const canRead = hasPermission(user, "products.read");
  const canWrite = hasPermission(user, "products.write");
  const push = useToastStore((s) => s.push);

  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [applied, setApplied] = useState({ q: "", status: "" });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [rows, setRows] = useState<AdminProductListItem[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);

  const load = useCallback(async () => {
    if (!canRead) {
      setLoading(false);
      setError("Missing permission: products.read");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchProducts({
        q: applied.q || undefined,
        status: applied.status || undefined,
        page,
        per_page: 20,
      });
      setRows(res.data);
      setPagination(res.meta?.pagination ?? null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canRead, applied, page]);

  useEffect(() => {
    void load();
  }, [load]);

  function applyFilters() {
    setPage(1);
    setApplied({ q, status });
  }

  async function onDelete(id: number, name: string) {
    if (!canWrite) return;
    if (!window.confirm(`Delete product “${name}”?`)) return;
    try {
      await deleteProduct(id);
      push("Product deleted", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Delete failed", "error");
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Products" }]} />
      <PageHeader
        title="Products"
        description="Catalog operations — search, filter, and edit."
        actions={
          canWrite ? (
            <Link href="/products/new">
              <Button>Create product</Button>
            </Link>
          ) : undefined
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input
          label="Search"
          placeholder="Name, SKU, or slug"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="min-w-[220px]"
        />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="draft">draft</option>
          <option value="active">active</option>
          <option value="archived">archived</option>
        </Select>
        <Button onClick={applyFilters}>Apply</Button>
        <Button variant="secondary" onClick={() => void load()}>
          Refresh
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? (
        <EmptyState
          title="No products found"
          actionLabel={canWrite ? "Create product" : undefined}
          onAction={canWrite ? () => (window.location.href = "/products/new") : undefined}
        />
      ) : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5 font-medium">Image</th>
                <th className="px-3 py-2.5 font-medium">Product</th>
                <th className="px-3 py-2.5 font-medium">SKU</th>
                <th className="px-3 py-2.5 font-medium">Category</th>
                <th className="px-3 py-2.5 font-medium">Price</th>
                <th className="px-3 py-2.5 font-medium">Stock</th>
                <th className="px-3 py-2.5 font-medium">Status</th>
                <th className="px-3 py-2.5 font-medium">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((product) => (
                <tr key={product.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5">
                    <div className="relative h-10 w-10 overflow-hidden rounded bg-[var(--admin-surface-muted)]">
                      {product.thumbnail_url ? (
                        <Image
                          src={product.thumbnail_url}
                          alt={product.name}
                          fill
                          className="object-cover"
                          unoptimized
                        />
                      ) : null}
                    </div>
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="font-medium">{product.name}</div>
                    <div className="text-xs text-[var(--admin-muted)]">{product.product_type}</div>
                  </td>
                  <td className="px-3 py-2.5 font-mono text-xs">{product.sku}</td>
                  <td className="px-3 py-2.5 text-xs">
                    {product.categories?.length ? product.categories.join(", ") : "—"}
                  </td>
                  <td className="px-3 py-2.5">{formatMoney(product.price)}</td>
                  <td className="px-3 py-2.5">{product.stock_qty ?? "—"}</td>
                  <td className="px-3 py-2.5">
                    <Badge tone={statusTone(product.status)}>{product.status}</Badge>
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="flex gap-2">
                      {canWrite ? (
                        <Link
                          href={`/products/${product.id}/edit`}
                          className="text-[var(--admin-primary)] hover:underline"
                        >
                          Edit
                        </Link>
                      ) : (
                        <span className="text-[var(--admin-muted)]">View only</span>
                      )}
                      {canWrite ? (
                        <button
                          type="button"
                          className="text-[var(--admin-danger)] hover:underline"
                          onClick={() => void onDelete(product.id, product.name)}
                        >
                          Delete
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {pagination ? (
            <div className="flex items-center justify-between gap-3 border-t border-[var(--admin-border)] px-3 py-2 text-xs text-[var(--admin-muted)]">
              <span>
                Page {pagination.current_page} of {pagination.last_page} · {pagination.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
