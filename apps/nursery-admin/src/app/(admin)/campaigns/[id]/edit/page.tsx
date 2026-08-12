"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { CampaignForm } from "@/features/campaigns/CampaignForm";
import { ApiError } from "@/lib/api/client";
import { fetchCampaign, type AdminCampaign } from "@/lib/api/campaigns";

export default function EditCampaignPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const [campaign, setCampaign] = useState<AdminCampaign | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        const res = await fetchCampaign(id);
        setCampaign(res.data);
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
          { label: "Campaigns", href: "/campaigns" },
          { label: campaign?.title ?? `#${id}` },
        ]}
      />
      <PageHeader title={campaign ? `Edit ${campaign.title}` : "Edit campaign"} />
      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} /> : null}
      {!loading && !error && campaign ? (
        <CampaignForm mode="edit" initial={campaign} campaignId={id} />
      ) : null}
    </div>
  );
}
