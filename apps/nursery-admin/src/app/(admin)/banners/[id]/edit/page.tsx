"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { BannerForm } from "@/features/banners/BannerForm";
import { ApiError } from "@/lib/api/client";
import { fetchBanner, type AdminBanner } from "@/lib/api/banners";

export default function EditBannerPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const [banner, setBanner] = useState<AdminBanner | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetchBanner(id);
        setBanner(res.data);
      } catch (err) {
        setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Banners", href: "/banners" },
          { label: banner?.title ?? `#${id}` },
        ]}
      />
      <PageHeader title={banner ? `Edit ${banner.title}` : "Edit banner"} />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} /> : null}
      {!loading && !error && banner ? (
        <BannerForm mode="edit" initial={banner} bannerId={id} />
      ) : null}
    </div>
  );
}
