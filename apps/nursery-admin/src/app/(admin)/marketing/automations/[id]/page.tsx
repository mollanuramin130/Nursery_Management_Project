"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input } from "@/components/ui/Input";
import { PermissionGate } from "@/components/auth/PermissionGate";
import { ApiError } from "@/lib/api/client";
import {
  activateAutomation,
  dispatchAutomation,
  fetchAutomation,
  pauseAutomation,
  type MarketingAutomation,
} from "@/lib/api/marketing";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function AutomationDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "marketing.view");
  const push = useToastStore((s) => s.push);
  const [row, setRow] = useState<MarketingAutomation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [testIds, setTestIds] = useState("");

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: marketing.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchAutomation(id);
      setRow(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onActivate() {
    setBusy(true);
    try {
      await activateAutomation(id);
      push("Automation activated", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Activate failed", "error");
    } finally {
      setBusy(false);
    }
  }

  async function onPause() {
    setBusy(true);
    try {
      await pauseAutomation(id);
      push("Automation paused", "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Pause failed", "error");
    } finally {
      setBusy(false);
    }
  }

  async function onDispatch(test: boolean) {
    setBusy(true);
    try {
      const ids = test
        ? testIds
            .split(/[,\s]+/)
            .map((v) => Number(v.trim()))
            .filter((n) => Number.isFinite(n) && n > 0)
        : undefined;
      if (test && (!ids || ids.length === 0)) {
        push("Enter test user IDs", "error");
        return;
      }
      const res = await dispatchAutomation(id, ids);
      push(`Dispatch: sent ${res.data.sent}, skipped ${res.data.skipped}, failed ${res.data.failed}`, "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Dispatch failed", "error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Marketing", href: "/marketing" },
          { label: "Automations", href: "/marketing/automations" },
          { label: row?.name ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={row?.name ?? "Automation"}
        description={row ? `${row.type} · ${row.key}` : undefined}
        actions={
          row ? (
            <div className="flex flex-wrap gap-2">
              <PermissionGate permission="marketing.launch">
                {row.status !== "active" ? (
                  <Button disabled={busy} onClick={() => void onActivate()}>
                    Activate
                  </Button>
                ) : null}
              </PermissionGate>
              <PermissionGate permission="marketing.manage">
                {row.status === "active" ? (
                  <Button variant="secondary" disabled={busy} onClick={() => void onPause()}>
                    Pause
                  </Button>
                ) : null}
              </PermissionGate>
            </div>
          ) : null
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && row ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <FormSection title="Configuration">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Status</dt>
                <dd>
                  <Badge tone={row.status === "active" ? "success" : "warning"}>{row.status}</Badge>
                </dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Channels</dt>
                <dd>{(row.channels ?? []).join(", ") || "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Segment</dt>
                <dd>
                  {row.segment ? (
                    <Link href={`/customers/segments/${row.segment.id}`} className="text-[var(--admin-primary)] hover:underline">
                      {row.segment.name}
                    </Link>
                  ) : (
                    "—"
                  )}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Coupon</dt>
                <dd>{row.coupon?.code ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Campaign</dt>
                <dd>{row.campaign?.title ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Last run</dt>
                <dd>{formatDateTime(row.last_run_at)}</dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Delivery stats" description="From marketing_deliveries only.">
            <dl className="grid gap-2 text-sm sm:grid-cols-3">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Sent</dt>
                <dd className="text-lg font-semibold">{row.delivery_stats?.sent ?? 0}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Skipped</dt>
                <dd className="text-lg font-semibold">{row.delivery_stats?.skipped ?? 0}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Failed</dt>
                <dd className="text-lg font-semibold">{row.delivery_stats?.failed ?? 0}</dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Preview (not sent)">
            <p className="text-sm font-medium">{row.title_template || "—"}</p>
            <p className="mt-2 whitespace-pre-wrap text-sm text-[var(--admin-muted)]">
              {row.body_template || "—"}
            </p>
          </FormSection>

          <FormSection title="Dispatch / test mode">
            <p className="mb-2 text-xs text-[var(--admin-muted)]">
              Test mode sends only to listed customer user IDs (max 20). Does not activate production audience.
            </p>
            <Input
              placeholder="Test user IDs, e.g. 12, 34"
              value={testIds}
              onChange={(e) => setTestIds(e.target.value)}
            />
            <div className="mt-3 flex flex-wrap gap-2">
              <PermissionGate permission="marketing.launch">
                <Button variant="secondary" disabled={busy} onClick={() => void onDispatch(true)}>
                  Send test
                </Button>
                <Button disabled={busy || row.status !== "active"} onClick={() => void onDispatch(false)}>
                  Dispatch audience batch
                </Button>
              </PermissionGate>
            </div>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
