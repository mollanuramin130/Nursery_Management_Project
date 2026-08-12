"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { FormSection } from "@/components/ui/FormSection";
import { ApiError } from "@/lib/api/client";
import {
  fetchCustomer,
  fetchCustomer360,
  updateCustomerStatus,
  type AdminCustomer,
  type Customer360,
} from "@/lib/api/customers";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "blocked") return "danger" as const;
  return "neutral" as const;
}

export default function CustomerDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "users.manage");
  const can360 = hasPermission(user, ["customers.view", "users.manage"]);
  const push = useToastStore((s) => s.push);
  const [customer, setCustomer] = useState<AdminCustomer | null>(null);
  const [crm, setCrm] = useState<Customer360 | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingStatus, setPendingStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!canManage && !can360) {
      setLoading(false);
      setError("Missing permission: users.manage or customers.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      if (canManage) {
        const res = await fetchCustomer(id);
        setCustomer(res.data);
      }
      if (can360) {
        try {
          const z = await fetchCustomer360(id);
          setCrm(z.data);
          if (!canManage) {
            setCustomer({
              id: z.data.profile.id,
              name: z.data.profile.name,
              email: z.data.profile.email,
              phone: z.data.profile.phone,
              status: z.data.profile.status,
              roles: z.data.profile.roles,
              last_login_at: z.data.profile.last_login_at,
              created_at: z.data.profile.registered_at,
              total_orders: z.data.orders.total_orders,
              total_spent: z.data.orders.total_spent,
            });
          }
        } catch {
          // 360 optional if permission missing on older deploys
        }
      }
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [canManage, can360, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onConfirmStatus() {
    if (!pendingStatus || !customer) return;
    setBusy(true);
    try {
      await updateCustomerStatus(customer.id, pendingStatus);
      push(`Customer set to ${pendingStatus}`, "success");
      setPendingStatus(null);
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Update failed", "error");
    } finally {
      setBusy(false);
    }
  }

  const nextToggle =
    customer?.status === "active" ? "inactive" : customer ? "active" : null;
  const profile = crm?.profile;
  const orders = crm?.orders;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Customers", href: "/customers" },
          { label: customer?.name ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={customer?.name ?? "Customer"}
        description={customer?.email}
        actions={
          canManage && customer ? (
            <div className="flex flex-wrap gap-2">
              {nextToggle ? (
                <Button variant="secondary" onClick={() => setPendingStatus(nextToggle)}>
                  {nextToggle === "active" ? "Activate" : "Deactivate"}
                </Button>
              ) : null}
              {customer.status !== "blocked" ? (
                <Button variant="danger" onClick={() => setPendingStatus("blocked")}>
                  Block
                </Button>
              ) : (
                <Button variant="secondary" onClick={() => setPendingStatus("active")}>
                  Unblock
                </Button>
              )}
            </div>
          ) : null
        }
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && customer ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <FormSection title="Customer profile">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">ID</dt>
                <dd>{customer.id}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Status</dt>
                <dd>
                  <Badge tone={statusTone(customer.status)}>{customer.status}</Badge>
                </dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Phone</dt>
                <dd>{customer.phone ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Lifecycle</dt>
                <dd>{profile?.lifecycle_stage ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Registered</dt>
                <dd>{formatDateTime(profile?.registered_at ?? customer.created_at)}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Last login</dt>
                <dd>{formatDateTime(profile?.last_login_at ?? customer.last_login_at)}</dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Order summary" description={crm?.definitions?.total_spent}>
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Total orders</dt>
                <dd className="text-lg font-semibold">{orders?.total_orders ?? customer.total_orders ?? 0}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Total spent</dt>
                <dd className="text-lg font-semibold">
                  {formatMoney(orders?.total_spent ?? customer.total_spent ?? 0)}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">AOV</dt>
                <dd>{orders ? formatMoney(orders.average_order_value) : "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Last order</dt>
                <dd>
                  {orders?.last_order ? (
                    <Link href={`/orders/${orders.last_order.id}`} className="text-[var(--admin-primary)] hover:underline">
                      {orders.last_order.order_number}
                    </Link>
                  ) : (
                    "—"
                  )}
                </dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Activity">
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Wishlist items</dt>
                <dd>{crm?.activity.wishlist_count ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Reviews</dt>
                <dd>{crm?.activity.review_count ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Recent views</dt>
                <dd>{(crm?.activity.recently_viewed_product_ids ?? []).length}</dd>
              </div>
              <div>
                <dt className="text-xs text-[var(--admin-muted)]">Open cart</dt>
                <dd>{crm?.activity.open_cart ? "Yes" : "No"}</dd>
              </div>
            </dl>
          </FormSection>

          <FormSection title="Loyalty & subscriptions">
            <pre className="overflow-x-auto rounded bg-[var(--admin-surface)] p-2 text-xs">
              {JSON.stringify(
                { loyalty: crm?.loyalty ?? null, subscriptions: crm?.subscriptions ?? [] },
                null,
                2,
              )}
            </pre>
          </FormSection>

          <FormSection title="Marketing preferences">
            <pre className="overflow-x-auto rounded bg-[var(--admin-surface)] p-2 text-xs">
              {JSON.stringify(crm?.marketing.preferences ?? {}, null, 2)}
            </pre>
            {(crm?.marketing.coupon_redemptions ?? []).length > 0 ? (
              <ul className="mt-2 space-y-1 text-sm">
                {crm!.marketing.coupon_redemptions.map((r) => (
                  <li key={r.id}>
                    {r.coupon_code} · {r.discount_amount != null ? formatMoney(r.discount_amount) : "—"} ·{" "}
                    {formatDateTime(r.created_at)}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-[var(--admin-muted)]">No coupon redemptions.</p>
            )}
          </FormSection>

          <FormSection title="Recent orders" description="Links open order detail.">
            {(orders?.recent ?? customer.recent_orders ?? []).length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No recent orders.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="py-1.5 pr-3">Order</th>
                      <th className="py-1.5 pr-3">Status</th>
                      <th className="py-1.5 pr-3">Total</th>
                      <th className="py-1.5">Placed</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(orders?.recent ?? customer.recent_orders ?? []).map((o) => (
                      <tr key={o.id} className="border-t border-[var(--admin-border)]/60">
                        <td className="py-2 pr-3">
                          <Link href={`/orders/${o.id}`} className="text-[var(--admin-primary)] hover:underline">
                            {o.order_number}
                          </Link>
                        </td>
                        <td className="py-2 pr-3">
                          <Badge tone="neutral">{o.status}</Badge>
                        </td>
                        <td className="py-2 pr-3">{formatMoney(o.grand_total)}</td>
                        <td className="py-2 text-xs">{formatDateTime(o.placed_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </FormSection>
        </div>
      ) : null}

      <ConfirmDialog
        open={Boolean(pendingStatus)}
        title="Update customer status?"
        description={`Set ${customer?.email ?? "customer"} to “${pendingStatus ?? ""}”.`}
        danger={pendingStatus === "blocked" || pendingStatus === "inactive"}
        loading={busy}
        confirmLabel="Confirm"
        onCancel={() => setPendingStatus(null)}
        onConfirm={() => void onConfirmStatus()}
      />
    </div>
  );
}
