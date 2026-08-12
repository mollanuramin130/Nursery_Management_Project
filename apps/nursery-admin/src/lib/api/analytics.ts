import { apiGet } from "@/lib/api/client";
import { api } from "@/lib/api/client";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";
import { rangeToQuery } from "@/lib/analytics/range";
import { storage } from "@/lib/storage";

function qs(range: AnalyticsRangeParams, extra?: Record<string, string>) {
  const base = rangeToQuery(range);
  return extra ? { ...base, ...extra } : base;
}

export async function fetchAnalyticsOverview(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/overview", qs(range));
}

export async function fetchAnalyticsSales(range: AnalyticsRangeParams, paymentMethod?: string) {
  return apiGet<Record<string, unknown>>(
    "/admin/analytics/sales",
    qs(range, paymentMethod ? { payment_method: paymentMethod } : undefined),
  );
}

export async function fetchAnalyticsOrders(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/orders", qs(range));
}

export async function fetchAnalyticsProducts(range: AnalyticsRangeParams, sort = "revenue") {
  return apiGet<Record<string, unknown>>("/admin/analytics/products", qs(range, { sort }));
}

export async function fetchAnalyticsCategories(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/categories", qs(range));
}

export async function fetchAnalyticsCustomers(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/customers", qs(range));
}

export async function fetchAnalyticsInventory() {
  return apiGet<Record<string, unknown>>("/admin/analytics/inventory");
}

export async function fetchAnalyticsCampaigns() {
  return apiGet<Record<string, unknown>>("/admin/analytics/campaigns");
}

export async function fetchAnalyticsCoupons(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/coupons", qs(range));
}

export async function fetchAnalyticsReturns(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/returns", qs(range));
}

export async function fetchAnalyticsSeasonal(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/seasonal", qs(range));
}

export async function fetchAnalyticsPayments(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/payments", qs(range));
}

export async function fetchAnalyticsPlants(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/plants", qs(range));
}

export async function fetchAnalyticsReviews(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/reviews", qs(range));
}

export async function fetchAnalyticsSearch(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/search", qs(range));
}

export async function fetchAnalyticsCohorts(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/cohorts", qs(range));
}

export async function fetchAnalyticsAttention(range: AnalyticsRangeParams) {
  return apiGet<Record<string, unknown>>("/admin/analytics/attention", qs(range));
}

export async function downloadAnalyticsExport(
  type: string,
  range: AnalyticsRangeParams,
): Promise<void> {
  const params = qs(range, { type });
  const res = await api.get("/admin/analytics/export", {
    params,
    responseType: "blob",
    headers: {
      Authorization: `Bearer ${storage.getAccess() ?? ""}`,
    },
  });
  const blob = new Blob([res.data], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `greenleaf-${type}-export.csv`;
  a.click();
  URL.revokeObjectURL(url);
}
