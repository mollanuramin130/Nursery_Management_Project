"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { Input, Select, TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  adjustLoyalty,
  fetchLoyaltyAccounts,
  fetchLoyaltyDashboard,
  fetchLoyaltyTransactions,
  type LoyaltyAccountRow,
  type LoyaltyDashboard,
  type LoyaltyTx,
} from "@/lib/api/loyalty";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function LoyaltyPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "loyalty.view");
  const canAdjust = hasPermission(user, "loyalty.adjust");
  const push = useToastStore((s) => s.push);

  const [q, setQ] = useState("");
  const debouncedQ = useDebouncedValue(q, 350);
  const [accounts, setAccounts] = useState<LoyaltyAccountRow[]>([]);
  const [txs, setTxs] = useState<LoyaltyTx[]>([]);
  const [dash, setDash] = useState<LoyaltyDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [userId, setUserId] = useState("");
  const [points, setPoints] = useState("");
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: loyalty.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [a, t, d] = await Promise.all([
        fetchLoyaltyAccounts({ q: debouncedQ || undefined, page: 1, per_page: 30 }),
        fetchLoyaltyTransactions({ page: 1, per_page: 30 }),
        fetchLoyaltyDashboard(),
      ]);
      setAccounts(a.data);
      setTxs(t.data);
      setDash(d.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, debouncedQ]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAdjust(e: FormEvent) {
    e.preventDefault();
    if (!canAdjust) return;
    setSaving(true);
    try {
      await adjustLoyalty({
        user_id: Number(userId),
        points: Number(points),
        reason: reason.trim(),
        idempotency_key: `admin-adjust-${userId}-${points}-${Date.now()}`,
      });
      push("Loyalty adjusted", "success");
      setReason("");
      setPoints("");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Adjust failed", "error");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Loyalty" }]} />
      <PageHeader
        title="Loyalty"
        description="Points ledger foundation. Earn on delivery; reverse on refund. Checkout redemption not enabled."
      />

      {dash ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
          {[
            ["Accounts", dash.accounts],
            ["Outstanding", dash.points_outstanding],
            ["Earned", dash.points_earned],
            ["Reversed", dash.points_reversed],
            ["Per ₹", dash.points_per_rupee],
          ].map(([label, value]) => (
            <div
              key={String(label)}
              className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3"
            >
              <div className="text-xs uppercase text-[var(--admin-muted)]">{label}</div>
              <div className="text-2xl font-semibold tabular-nums">{value ?? 0}</div>
            </div>
          ))}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input label="Search customer" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name, email, user id" />
        <Button type="button" onClick={() => void load()}>
          Apply
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <div className="grid gap-4 xl:grid-cols-2">
          <div>
            <h2 className="mb-2 text-sm font-semibold uppercase text-[var(--admin-muted)]">Accounts</h2>
            {accounts.length === 0 ? (
              <EmptyState title="No loyalty accounts yet" />
            ) : (
              <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-3 py-2.5">Customer</th>
                      <th className="px-3 py-2.5">Balance</th>
                      <th className="px-3 py-2.5">Earned</th>
                      <th className="px-3 py-2.5">Updated</th>
                    </tr>
                  </thead>
                  <tbody>
                    {accounts.map((row) => (
                      <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-3 py-2.5">
                          {row.customer?.name ?? row.user_id}
                          <div className="text-xs text-[var(--admin-muted)]">{row.customer?.email}</div>
                        </td>
                        <td className="px-3 py-2.5 tabular-nums">{row.balance}</td>
                        <td className="px-3 py-2.5 tabular-nums">{row.lifetime_earned}</td>
                        <td className="px-3 py-2.5 text-xs">{formatDateTime(row.updated_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div>
            <h2 className="mb-2 text-sm font-semibold uppercase text-[var(--admin-muted)]">Recent ledger</h2>
            {txs.length === 0 ? (
              <EmptyState title="No transactions" />
            ) : (
              <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
                <table className="min-w-full text-left text-sm">
                  <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="px-3 py-2.5">Type</th>
                      <th className="px-3 py-2.5">Points</th>
                      <th className="px-3 py-2.5">Customer</th>
                      <th className="px-3 py-2.5">When</th>
                    </tr>
                  </thead>
                  <tbody>
                    {txs.map((row) => (
                      <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                        <td className="px-3 py-2.5">{row.type}</td>
                        <td className="px-3 py-2.5 tabular-nums">{row.points}</td>
                        <td className="px-3 py-2.5">{row.customer?.name ?? row.user_id ?? "—"}</td>
                        <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      ) : null}

      {canAdjust ? (
        <div className="mt-4">
          <FormSection title="Manual adjustment">
            <form className="grid gap-3 sm:grid-cols-2" onSubmit={onAdjust}>
              <Input label="User ID" value={userId} onChange={(e) => setUserId(e.target.value)} required />
              <Input label="Points (+/-)" value={points} onChange={(e) => setPoints(e.target.value)} required />
              <div className="sm:col-span-2">
                <TextArea label="Reason" value={reason} onChange={(e) => setReason(e.target.value)} required rows={2} />
              </div>
              <div>
                <Select label="Quick type" value="" onChange={() => undefined} disabled>
                  <option value="">ADJUST</option>
                </Select>
              </div>
              <div className="flex items-end">
                <Button type="submit" disabled={saving}>
                  Apply adjustment
                </Button>
              </div>
            </form>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
