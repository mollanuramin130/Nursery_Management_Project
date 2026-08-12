"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { CouponForm } from "@/features/coupons/CouponForm";
import { ApiError } from "@/lib/api/client";
import { fetchCoupon, type AdminCoupon } from "@/lib/api/coupons";

export default function EditCouponPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const [coupon, setCoupon] = useState<AdminCoupon | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetchCoupon(id);
        setCoupon(res.data);
      } catch (err) {
        setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Coupons", href: "/coupons" }, { label: coupon?.code ?? `#${id}` }]} />
      <PageHeader title={coupon ? `Edit ${coupon.code}` : "Edit coupon"} />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} /> : null}
      {!loading && !error && coupon ? <CouponForm mode="edit" initial={coupon} couponId={id} /> : null}
    </div>
  );
}
