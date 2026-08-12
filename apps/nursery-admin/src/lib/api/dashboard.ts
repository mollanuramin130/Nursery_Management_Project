import { apiGet } from "@/lib/api/client";
import type { DashboardSummary } from "@/lib/types";

export async function fetchDashboard() {
  return apiGet<DashboardSummary>("/admin/dashboard");
}
