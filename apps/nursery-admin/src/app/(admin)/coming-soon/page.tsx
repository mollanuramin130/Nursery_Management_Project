"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { PageHeader } from "@/components/feedback/States";

function ComingSoonInner() {
  const params = useSearchParams();
  const feature = params.get("feature") ?? "This module";

  return (
    <div>
      <Breadcrumbs
        items={[{ label: "Admin", href: "/dashboard" }, { label: feature }]}
      />
      <PageHeader
        title={`${feature} — coming soon`}
        description="This navigation item is permission-gated for later Admin phases. Backend APIs may already exist."
      />
      <div className="rounded-[var(--admin-radius-lg)] border border-dashed border-[var(--admin-border)] bg-white px-5 py-10 text-sm text-[var(--admin-muted)]">
        Phase 1 covers Dashboard, Orders, Products, and Categories. {feature} will be implemented
        in a future phase without rebuilding the customer Website or Android apps.
      </div>
    </div>
  );
}

export default function ComingSoonPage() {
  return (
    <Suspense fallback={<div className="text-sm text-[var(--admin-muted)]">Loading…</div>}>
      <ComingSoonInner />
    </Suspense>
  );
}
