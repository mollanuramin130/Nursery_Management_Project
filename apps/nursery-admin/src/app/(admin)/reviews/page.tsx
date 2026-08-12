"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { EmptyState, ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import {
  fetchReviews,
  fetchReviewsDashboard,
  type AdminReview,
  type ReviewsDashboard,
} from "@/lib/api/reviews";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

function tone(status: string) {
  if (status === "approved") return "success" as const;
  if (status === "rejected" || status === "hidden") return "danger" as const;
  return "warning" as const;
}

export default function ReviewsPage() {
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "reviews.view");
  const [status, setStatus] = useState("pending");
  const [q, setQ] = useState("");
  const [rows, setRows] = useState<AdminReview[]>([]);
  const [dash, setDash] = useState<ReviewsDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      setError("Missing permission: reviews.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [list, d] = await Promise.all([
        fetchReviews({ status: status || undefined, q: q || undefined, page, per_page: 30 }),
        fetchReviewsDashboard(),
      ]);
      setRows(list.data);
      setDash(d.data);
      setLastPage((list.meta?.pagination as { last_page?: number } | undefined)?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, status, q, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs items={[{ label: "Admin", href: "/dashboard" }, { label: "Reviews" }]} />
      <PageHeader title="Reviews" description="Moderate customer product reviews before they affect public ratings." />

      {dash ? (
        <div className="mb-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-5">
          {[
            ["Pending", dash.pending],
            ["Approved", dash.approved],
            ["Rejected", dash.rejected],
            ["Hidden", dash.hidden],
            ["Avg rating", dash.avg_rating_approved],
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
        <Input label="Search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Review, product, customer" />
        <Select
          label="Status"
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value);
          }}
        >
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="hidden">Hidden</option>
        </Select>
        <Button type="button" onClick={() => void load()}>
          Apply
        </Button>
      </div>

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        rows.length === 0 ? (
          <EmptyState title="No reviews" />
        ) : (
          <div className="overflow-x-auto rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-[var(--admin-border)] bg-[var(--admin-surface-muted)] text-xs uppercase text-[var(--admin-muted)]">
                <tr>
                  <th className="px-3 py-2.5">Review</th>
                  <th className="px-3 py-2.5">Product</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Rating</th>
                  <th className="px-3 py-2.5">Verified</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Created</th>
                  <th className="px-3 py-2.5">Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-[var(--admin-border)]/70">
                    <td className="px-3 py-2.5 font-mono text-xs">#{row.id}</td>
                    <td className="px-3 py-2.5">{row.product?.name ?? "—"}</td>
                    <td className="px-3 py-2.5">{row.customer?.name ?? "—"}</td>
                    <td className="px-3 py-2.5 tabular-nums">{row.rating}★</td>
                    <td className="px-3 py-2.5">{row.verified_purchase ? "Yes" : "—"}</td>
                    <td className="px-3 py-2.5">
                      <Badge tone={tone(row.status)}>{row.status}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-xs">{formatDateTime(row.created_at)}</td>
                    <td className="px-3 py-2.5">
                      <Link className="text-[var(--admin-primary)] hover:underline" href={`/reviews/${row.id}`}>
                        Open
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : null}

      {lastPage > 1 ? (
        <div className="mt-3 flex gap-2">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="self-center text-sm text-[var(--admin-muted)]">
            Page {page} / {lastPage}
          </span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      ) : null}
    </div>
  );
}
