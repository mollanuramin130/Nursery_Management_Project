"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";
import { CampaignForm } from "@/features/campaigns/CampaignForm";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuthStore } from "@/store/auth";

export default function NewCampaignPage() {
  const user = useAuthStore((s) => s.user);
  const router = useRouter();
  const can = hasPermission(user, "campaigns.manage");
  useEffect(() => {
    if (user && !can) router.replace("/campaigns");
  }, [user, can, router]);
  if (!can) return <p className="text-sm text-[var(--admin-muted)]">Missing permission</p>;
  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Campaigns", href: "/campaigns" },
          { label: "Create" },
        ]}
      />
      <PageHeader title="Create campaign" />
      <CampaignForm mode="create" />
    </div>
  );
}
