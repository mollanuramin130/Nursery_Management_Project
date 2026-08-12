"use client";

import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  createSubscriptionPlan,
  fetchSubscriptionPlans,
  updateSubscriptionPlan,
  type SubscriptionPlan,
} from "@/lib/api/subscriptions";
import { hasPermission } from "@/lib/auth/permissions";
import { formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function SubscriptionPlansPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "subscriptions.view");
  const canManage = hasPermission(user, "subscriptions.manage");
  const push = useToastStore((s) => s.push);
  const [rows, setRows] = useState<SubscriptionPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [productId, setProductId] = useState("");
  const [name, setName] = useState("");
  const [frequency, setFrequency] = useState("MONTHLY");
  const [unitPrice, setUnitPrice] = useState("");

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: subscriptions.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const list = await fetchSubscriptionPlans({ per_page: 50 });
      setRows(list.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void load();
  }, [load]);

  async function create() {
    if (!canManage) return;
    try {
      await createSubscriptionPlan({
        product_id: Number(productId),
        name,
        frequency,
        unit_price: Number(unitPrice),
        status: "active",
      });
      push("Plan created", "success");
      setName("");
      setUnitPrice("");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    }
  }

  async function toggle(plan: SubscriptionPlan) {
    if (!canManage) return;
    const next = plan.status === "active" ? "archived" : "active";
    try {
      await updateSubscriptionPlan(plan.id, { status: next });
      push(`Plan ${next}`, "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Failed", "error");
    }
  }

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Subscriptions", href: "/subscriptions" },
          { label: "Plans" },
        ]}
      />
      <PageHeader title="Subscription plans" description="Attach recurring plans to catalog products. Price edits affect new subscriptions only." />

      {canManage ? (
        <div className="mb-4 grid gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3 sm:grid-cols-4">
          <Input label="Product ID" value={productId} onChange={(e) => setProductId(e.target.value)} />
          <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} />
          <Select label="Frequency" value={frequency} onChange={(e) => setFrequency(e.target.value)}>
            {["WEEKLY", "BIWEEKLY", "MONTHLY", "QUARTERLY", "YEARLY"].map((f) => (
              <option key={f} value={f}>
                {f}
              </option>
            ))}
          </Select>
          <Input label="Unit price" value={unitPrice} onChange={(e) => setUnitPrice(e.target.value)} />
          <div className="sm:col-span-4">
            <Button onClick={() => void create()}>Create active plan</Button>
          </div>
        </div>
      ) : null}

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No plans" /> : null}

      {!loading && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-sm">
            <thead className="bg-[var(--admin-surface-2)] text-left text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2">Plan</th>
                <th className="px-3 py-2">Product</th>
                <th className="px-3 py-2">Frequency</th>
                <th className="px-3 py-2">Price</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-[var(--admin-border)]">
                  <td className="px-3 py-2 font-medium">{r.name}</td>
                  <td className="px-3 py-2">
                    {r.product_name} (#{r.product_id})
                  </td>
                  <td className="px-3 py-2">{r.frequency}</td>
                  <td className="px-3 py-2">{formatMoney(r.unit_price)}</td>
                  <td className="px-3 py-2">
                    <Badge>{r.status}</Badge>
                  </td>
                  <td className="px-3 py-2">
                    {canManage ? (
                      <Button variant="secondary" onClick={() => void toggle(r)}>
                        {r.status === "active" ? "Archive" : "Activate"}
                      </Button>
                    ) : (
                      "—"
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
