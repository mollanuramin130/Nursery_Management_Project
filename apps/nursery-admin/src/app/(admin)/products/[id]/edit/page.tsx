"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { ProductForm } from "@/features/products/ProductForm";
import { ApiError } from "@/lib/api/client";
import { fetchProduct } from "@/lib/api/products";
import { hasPermission } from "@/lib/auth/permissions";
import type { AdminProductDetail } from "@/lib/types";
import { useAuthStore } from "@/store/auth";

export default function EditProductPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const canWrite = hasPermission(user, "products.write");
  const canRead = hasPermission(user, "products.read");

  const [product, setProduct] = useState<AdminProductDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (user && !canWrite) router.replace("/products");
  }, [user, canWrite, router]);

  const load = useCallback(async () => {
    if (!canRead || !Number.isFinite(id)) {
      setLoading(false);
      setError("Missing permission or invalid product id");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchProduct(id);
      setProduct(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canRead, id]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Products", href: "/products" },
          { label: product?.name ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={product ? `Edit: ${product.name}` : "Edit product"}
        description="Update catalog fields via Admin API."
      />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && product && canWrite ? (
        <ProductForm mode="edit" productId={product.id} initial={product} />
      ) : null}
    </div>
  );
}
