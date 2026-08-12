"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";
import { CouponForm } from "@/features/coupons/CouponForm";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

export default function NewCouponPage() {
  const user = useAuthStore((s) => s.user);
  const router = useRouter();
  const can = hasPermission(user, "campaigns.manage");
  useEffect(() => {
    if (user && !can) router.replace("/coupons");
  }, [user, can, router]);
  if (!can) return <p className="text-sm text-[var(--admin-muted)]">Missing permission</p>;
  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Coupons", href: "/coupons" }, { label: "Create" }]} />
      <PageHeader title="Create coupon" />
      <CouponForm mode="create" />
    </div>
  );
}
