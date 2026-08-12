"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { ErrorState, LoadingBlock, PageHeader } from "@/components/feedback/States";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { FormSection } from "@/components/ui/FormSection";
import { TextArea } from "@/components/ui/Input";
import { ApiError } from "@/lib/api/client";
import { fetchReview, moderateReview, type AdminReview } from "@/lib/api/reviews";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format";
import { useAuthStore } from "@/store/auth";
import { useToastStore } from "@/store/toast";

export default function ReviewDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canView = hasPermission(user, "reviews.view");
  const canModerate = hasPermission(user, "reviews.moderate");
  const push = useToastStore((s) => s.push);

  const [review, setReview] = useState<AdminReview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!canView || !Number.isFinite(id)) {
      setLoading(false);
      setError(canView ? "Invalid review" : "Missing permission: reviews.view");
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetchReview(id);
      setReview(res.data);
    } catch (err) {
      setError(err instanceof ApiError || err instanceof Error ? err.message : "Failed to load");
    } finally {
      setLoading(false);
    }
  }, [canView, id]);

  useEffect(() => {
    void load();
  }, [load]);

  async function moderate(status: "approved" | "rejected" | "hidden") {
    setBusy(true);
    try {
      await moderateReview(id, { status, note: note.trim() || undefined });
      push(`Review ${status}`, "success");
      await load();
    } catch (err) {
      push(err instanceof Error ? err.message : "Moderation failed", "error");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingBlock />;
  if (error || !review) return <ErrorState message={error ?? "Not found"} onRetry={() => void load()} />;

  return (
    <div>
      <Breadcrumbs
        items={[
          { label: "Admin", href: "/dashboard" },
          { label: "Reviews", href: "/reviews" },
          { label: `#${review.id}` },
        ]}
      />
      <PageHeader
        title={`Review #${review.id}`}
        description={`${review.product?.name ?? "Product"} · ${review.customer?.name ?? "Customer"}`}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <FormSection title="Review">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-[var(--admin-muted)]">Status</dt>
              <dd>
                <Badge tone={review.status === "approved" ? "success" : review.status === "pending" ? "warning" : "danger"}>
                  {review.status}
                </Badge>
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-[var(--admin-muted)]">Rating</dt>
              <dd className="tabular-nums">{review.rating} / 5</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-[var(--admin-muted)]">Verified purchase</dt>
              <dd>{review.verified_purchase ? "Yes" : "No"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-[var(--admin-muted)]">Created</dt>
              <dd>{formatDateTime(review.created_at)}</dd>
            </div>
            {review.title ? (
              <div>
                <dt className="text-[var(--admin-muted)]">Title</dt>
                <dd className="font-medium">{review.title}</dd>
              </div>
            ) : null}
            <div>
              <dt className="text-[var(--admin-muted)]">Comment</dt>
              <dd className="whitespace-pre-wrap">{review.body || "—"}</dd>
            </div>
          </dl>
        </FormSection>

        <FormSection title="Customer & product">
          <dl className="space-y-2 text-sm">
            <div>
              <dt className="text-[var(--admin-muted)]">Customer</dt>
              <dd>
                {review.customer?.name}
                <div className="text-xs text-[var(--admin-muted)]">{review.customer?.email}</div>
              </dd>
            </div>
            <div>
              <dt className="text-[var(--admin-muted)]">Product</dt>
              <dd>
                {review.product?.name}
                <div className="text-xs text-[var(--admin-muted)]">{review.product?.sku}</div>
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-[var(--admin-muted)]">Order</dt>
              <dd>{review.order_id ?? "—"}</dd>
            </div>
          </dl>
        </FormSection>
      </div>

      {review.images && review.images.length > 0 ? (
        <div className="mt-4">
          <FormSection title="Images">
            <div className="flex flex-wrap gap-2">
              {review.images.map((url) => (
                // eslint-disable-next-line @next/next/no-img-element
                <img key={url} src={url} alt="" className="h-24 w-24 rounded object-cover" />
              ))}
            </div>
          </FormSection>
        </div>
      ) : null}

      {canModerate ? (
        <div className="mt-4">
          <FormSection title="Moderation">
            <TextArea label="Note (optional)" value={note} onChange={(e) => setNote(e.target.value)} rows={3} />
            <div className="mt-3 flex flex-wrap gap-2">
              <Button disabled={busy} onClick={() => void moderate("approved")}>
                Approve
              </Button>
              <Button variant="secondary" disabled={busy} onClick={() => void moderate("rejected")}>
                Reject
              </Button>
              <Button variant="secondary" disabled={busy} onClick={() => void moderate("hidden")}>
                Hide
              </Button>
            </div>
          </FormSection>
        </div>
      ) : null}
    </div>
  );
}
