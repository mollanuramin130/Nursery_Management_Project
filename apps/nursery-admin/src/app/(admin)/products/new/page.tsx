"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";
import { ProductForm } from "@/features/products/ProductForm";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

export default function NewProductPage() {
  const user = useAuthStore((s) => s.user);
  const router = useRouter();
  const canWrite = hasPermission(user, "products.write");

  useEffect(() => {
    if (user && !canWrite) router.replace("/products");
  }, [user, canWrite, router]);

  if (!canWrite) {
    return <p className="text-sm text-[var(--admin-muted)]">Missing permission: products.write</p>;
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Products", href: "/products" },
          { label: "Create" },
        ]}
      />
      <PageHeader title="Create product" description="Uses existing Admin product API fields." />
      <ProductForm mode="create" />
    </div>
  );
}
