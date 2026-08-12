"use client";

import { Suspense, useCallback, useState } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsCampaigns, fetchAnalyticsCoupons } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function CampaignsInner() {
  const [couponRows, setCouponRows] = useState<Array<Record<string, unknown>>>([]);
  const load = useCallback(async (range: AnalyticsRangeParams) => {
    const campaigns = await fetchAnalyticsCampaigns();
    try {
      const coupons = await fetchAnalyticsCoupons(range);
      setCouponRows((coupons.data.rows as Array<Record<string, unknown>>) ?? []);
    } catch {
      setCouponRows([]);
    }
    return campaigns;
  }, []);

  return (
    <AnalyticsShell
      title="Campaign analytics"
      description="Campaign order attribution is not available in the current schema."
      exportType="coupons"
      load={load}
    >
      {({ data }) => (
        <div className="space-y-4">
          <div className="rounded border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
            <p className="font-semibold">Campaign attribution unsupported</p>
            <p className="mt-1">{String(data?.reason ?? "")}</p>
            <p className="mt-1 text-xs">See docs/PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md</p>
          </div>
          <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
            <h2 className="mb-3 text-sm font-semibold">Coupon performance (not campaign attribution)</h2>
            <AnalyticsTable
              columns={[
                { key: "coupon_code", label: "Code" },
                { key: "redemptions", label: "Redemptions", align: "right" },
                { key: "orders", label: "Orders", align: "right" },
                {
                  key: "revenue",
                  label: "Revenue",
                  align: "right",
                  render: (r) => formatMoney(Number(r.revenue ?? 0)),
                },
                {
                  key: "discount_total",
                  label: "Discount",
                  align: "right",
                  render: (r) => formatMoney(Number(r.discount_total ?? 0)),
                },
              ]}
              rows={couponRows}
            />
          </section>
        </div>
      )}
    </AnalyticsShell>
  );
}

export default function CampaignsAnalyticsPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm">Loading…</p>}>
      <CampaignsInner />
    </Suspense>
  );
}
