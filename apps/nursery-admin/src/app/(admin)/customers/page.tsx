"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchCustomers, type AdminCustomer } from "@/lib/api/customers";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { Pagination } from "@/lib/types";
import { useAuthStore } from "@/store/auth";

function statusTone(status: string) {
  if (status === "active") return "success" as const;
  if (status === "blocked") return "danger" as const;
  return "neutral" as const;
}

export default function CustomersPage() {
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, "users.manage");
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [applied, setApplied] = useState({ q: "", status: "" });
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<AdminCustomer[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!canManage) {
      setLoading(false);
      setError("Missing permission: users.manage");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCustomers({
        q: applied.q || undefined,
        status: applied.status || undefined,
        page,
        per_page: 20,
      });
      setRows(res.data);
      setPagination(res.meta?.pagination ?? null);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canManage, applied, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Customers" }]} />
      <PageHeader
        title="Customers"
        description="Customer accounts (role=customer) from Admin users API."
      />

      <div className="mb-4 flex flex-wrap items-end gap-2 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
        <Input
          label="Search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="min-w-[200px]"
          placeholder="Name, email, phone"
        />
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="active">active</option>
          <option value="inactive">inactive</option>
          <option value="blocked">blocked</option>
        </Select>
        <Button
          onClick={() => {
            setPage(1);
            setApplied({ q, status });
          }}
        >
          Apply
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyState title="No customers" /> : null}

      {!loading && !error && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
              <tr>
                <th className="px-3 py-2.5">Customer</th>
                <th className="px-3 py-2.5">Phone</th>
                <th className="px-3 py-2.5">Orders</th>
                <th className="px-3 py-2.5">Spent</th>
                <th className="px-3 py-2.5">Last login</th>
                <th className="px-3 py-2.5">Status</th>
                <th className="px-3 py-2.5">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                  <td className="px-3 py-2.5">
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-[var(--admin-muted)]">{row.email}</div>
                  </td>
                  <td className="px-3 py-2.5 text-xs">{row.phone ?? "—"}</td>
                  <td className="px-3 py-2.5">{row.total_orders ?? "—"}</td>
                  <td className="px-3 py-2.5">
                    {row.total_spent != null ? formatMoney(row.total_spent) : "—"}
                  </td>
                  <td className="px-3 py-2.5 text-xs">{formatDateTime(row.last_login_at)}</td>
                  <td className="px-3 py-2.5">
                    <Badge tone={statusTone(row.status)}>{row.status}</Badge>
                  </td>
                  <td className="px-3 py-2.5">
                    <Link
                      href={`/customers/${row.id}`}
                      className="text-[var(--admin-primary)] hover:underline"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {pagination ? (
            <div className="flex items-center justify-between gap-3 border-t border-[var(--admin-border)] px-3 py-2 text-xs text-[var(--admin-muted)]">
              <span>
                Page {pagination.current_page} of {pagination.last_page} · {pagination.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
