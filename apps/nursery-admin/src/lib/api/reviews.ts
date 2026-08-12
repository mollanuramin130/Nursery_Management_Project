import { apiGet, apiSend } from "@/lib/api/client";

export type AdminReview = {
  id: number;
  product?: { id?: number; name?: string | null; sku?: string | null; slug?: string | null };
  customer?: { id?: number; name?: string | null; email?: string | null };
  order_id?: number | null;
  rating: number;
  title?: string | null;
  body?: string | null;
  status: string;
  verified_purchase?: boolean;
  moderation_note?: string | null;
  images?: string[];
  created_at?: string | null;
  moderated_at?: string | null;
};

export type ReviewsDashboard = {
  pending?: number;
  approved?: number;
  rejected?: number;
  hidden?: number;
  total?: number;
  avg_rating_approved?: number;
};

export async function fetchReviewsDashboard() {
  return apiGet<ReviewsDashboard>("/admin/reviews/dashboard");
}

export async function fetchReviews(params?: {
  status?: string;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  return apiGet<AdminReview[]>("/admin/reviews", params as Record<string, unknown>);
}

export async function fetchReview(id: number) {
  return apiGet<AdminReview>(`/admin/reviews/${id}`);
}

export async function moderateReview(
  id: number,
  body: { status: "approved" | "rejected" | "hidden"; note?: string },
) {
  return apiSend<AdminReview>("post", `/admin/reviews/${id}/moderate`, body);
}
