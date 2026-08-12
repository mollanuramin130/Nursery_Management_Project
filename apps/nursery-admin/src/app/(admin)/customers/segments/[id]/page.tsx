"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { ApiError } from "@/lib/api/client";
import {
  fetchSegment,
  fetchSegmentMembers,
  type CustomerSegment,
  type SegmentMember,
} from "@/lib/api/segments";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";

export default function SegmentDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const can = hasPermission(user, "customers.segment");
  const [segment, setSegment] = useState<CustomerSegment | null>(null);
  const [members, setMembers] = useState<SegmentMember[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!can) {
      setLoading(false);
      setError("Missing permission: customers.segment");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [segRes, memRes] = await Promise.all([
        fetchSegment(id),
        fetchSegmentMembers(id, { page, per_page: 20 }),
      ]);
      setSegment(segRes.data);
      setMembers(memRes.data);
      const pag = (memRes.meta as { pagination?: { total?: number; last_page?: number } })?.pagination;
      setTotal(pag?.total ?? segRes.data.member_count ?? 0);
      setLastPage(pag?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed");
    } finally {
      setLoading(false);
    }
  }, [can, id, page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Customers", href: "/customers" },
          { label: "Segments", href: "/customers/segments" },
          { label: segment?.name ?? `#${id}` },
        ]}
      />
      <PageHeader
        title={segment?.name ?? "Segment"}
        description={segment?.description ?? segment?.key}
      />

      {loading ? <LoadingBlock /> : null}
      {error ? <ErrorState message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && segment ? (
        <div className="grid gap-4">
          <FormSection title="Criteria">
            <div className="mb-2 flex flex-wrap gap-2 text-sm">
              <Badge tone={segment.status === "active" ? "success" : "neutral"}>{segment.status}</Badge>
              <span className="text-[var(--admin-muted)]">Members: {segment.member_count ?? total}</span>
              <span className="text-xs text-[var(--admin-muted)]">
                Updated {formatDateTime(segment.updated_at)}
              </span>
            </div>
            <ul className="list-disc space-y-1 pl-5 text-sm">
              {(segment.criteria_json?.all ?? []).map((r, i) => (
                <li key={`${r.field}-${i}`}>
                  <code>
                    {r.field} {r.op} {String(r.value)}
                  </code>
                </li>
              ))}
            </ul>
          </FormSection>

          <FormSection title="Members (paginated)">
            {members.length === 0 ? (
              <p className="text-sm text-[var(--admin-muted)]">No members match right now.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="text-xs uppercase text-[var(--admin-muted)]">
                    <tr>
                      <th className="py-1.5 pr-3">Customer</th>
                      <th className="py-1.5 pr-3">Email</th>
                      <th className="py-1.5 pr-3">Status</th>
                      <th className="py-1.5">Registered</th>
                    </tr>
                  </thead>
                  <tbody>
                    {members.map((m) => (
                      <tr key={m.id} className="border-t border-[var(--admin-border)]/60">
                        <td className="py-2 pr-3">
                          <Link href={`/customers/${m.id}`} className="text-[var(--admin-primary)] hover:underline">
                            {m.name}
                          </Link>
                        </td>
                        <td className="py-2 pr-3">{m.email}</td>
                        <td className="py-2 pr-3">{m.status}</td>
                        <td className="py-2 text-xs">{formatDateTime(m.registered_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <div className="mt-3 flex items-center gap-2">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <span className="text-xs text-[var(--admin-muted)]">
                Page {page} / {lastPage} · {total} total
              </span>
              <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
