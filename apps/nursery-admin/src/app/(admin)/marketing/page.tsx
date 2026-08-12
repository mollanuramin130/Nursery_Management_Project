"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { ApiError } from "@/lib/api/client";
import {
  fetchMarketingDashboard,
  type MarketingDashboard,
} from "@/lib/api/marketing";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

export default function MarketingDashboardPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "marketing.view");
  const [data, setData] = useState<MarketingDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: marketing.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchMarketingDashboard();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Marketing" }]} />
      <PageHeader
        title="Marketing hub"
        description="Automations, segments, merchandising campaigns, and delivery activity."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href="/marketing/automations">
              <Button variant="secondary">Automations</Button>
            </Link>
            <Link href="/customers/segments">
              <Button variant="secondary">Segments</Button>
            </Link>
            <Link href="/campaigns">
              <Button variant="secondary">Catalog campaigns</Button>
            </Link>
          </div>
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && data ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <FormSection title="Automations">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Total</dt>
                <dd className="text-lg font-semibold">{data.automations.total}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Draft</dt>
                <dd className="text-lg font-semibold">{data.automations.draft}</dd>
              </div>
            </dl>
            <div className="mt-3 space-y-2">
              {(data.automations.active ?? []).length === 0 ? (
                <p className="text-sm text-[var(--admin-muted)]">No active automations.</p>
              ) : (
                data.automations.active.map((a) => (
                  <div key={a.id} className="flex items-center justify-between gap-2 text-sm">
                    <Link href={`/marketing/automations/${a.id}`} className="text-[var(--admin-primary)] hover:underline">
                      {a.name}
                    </Link>
                    <Badge tone="success">{a.type}</Badge>
                  </div>
                ))
              )}
            </div>
          </FormSection>

          <FormSection title="Attribution" description={data.attribution.note}>
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Supported</dt>
                <dd>{data.attribution.supported ? "Yes (orders.campaign_id)" : "No"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Attributed orders</dt>
                <dd className="text-lg font-semibold">{data.attribution.orders_with_campaign_id}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Attributed revenue</dt>
                <dd className="text-lg font-semibold">
                  {formatMoney(data.attribution.revenue_with_campaign_id)}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Cart inactivity</dt>
                <dd>{data.config.abandoned_cart_hours}h</dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Segments (sample)">
            {(data.segments ?? []).length === 0 ? (
              <EmptyState title="No segments" description="Seed defaults with marketing:seed or create one." />
            ) : (
              <ul className="space-y-1 text-sm">
                {data.segments.map((s) => (
                  <li key={s.id}>
                    <Link href={`/customers/segments/${s.id}`} className="text-[var(--admin-primary)] hover:underline">
                      {s.name}
                    </Link>
                    <span className="ml-2 text-xs text-[var(--admin-muted)]">{s.key}</span>
                  </li>
                ))}
              </ul>
            )}
          </FormSection>

          <FormSection title="Merchandising campaigns">
            {(data.merchandising_campaigns ?? []).length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No catalog campaigns.</p>
            ) : (
              <ul className="space-y-2 text-sm">
                {data.merchandising_campaigns.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-2">
                    <Link href={`/campaigns/${c.id}/edit`} className="text-[var(--admin-primary)] hover:underline">
                      {c.title}
                    </Link>
                    <Badge tone={c.status === "active" ? "success" : "neutral"}>{c.status}</Badge>
                  </li>
                ))}
              </ul>
            )}
          </FormSection>

          <FormSection title="Recent deliveries" description="Last 20 delivery records — not fabricated opens/clicks.">
            {(data.deliveries_last_20 ?? []).length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No deliveries yet.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="py-1.5 pr-3">ID</th>
                      <th className="py-1.5 pr-3">User</th>
                      <th className="py-1.5 pr-3">Status</th>
                      <th className="py-1.5">When</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.deliveries_last_20.map((d) => (
                      <tr key={d.id} className="border-t border-[var(--admin-border)]/60">
                        <td className="py-2 pr-3">{d.id}</td>
                        <td className="py-2 pr-3">
                          <Link href={`/customers/${d.user_id}`} className="hover:underline">
                            #{d.user_id}
                          </Link>
                        </td>
                        <td className="py-2 pr-3">
                          <Badge tone={d.status === "sent" ? "success" : d.status === "failed" ? "danger" : "neutral"}>
                            {d.status}
                          </Badge>
                          {d.skip_reason ? (
                            <span className="ml-1 text-xs text-[var(--admin-muted)]">{d.skip_reason}</span>
                          ) : null}
                        </td>
                        <td className="py-2 text-xs">{formatDateTime(d.sent_at ?? d.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
