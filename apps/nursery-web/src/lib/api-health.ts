/**
 * Lightweight API readiness probe (no secrets).
 * Uses fetch directly so auth interceptors never run.
 */
export type ApiHealthState =
  | { status: "checking" }
  | { status: "ok" }
  | { status: "down"; detail: string };

export async function probeApiHealth(apiBaseUrl: string): Promise<ApiHealthState> {
  const base = apiBaseUrl.replace(/\/$/, "");
  const url = `${base}/health/ready`;
  try {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 8000);
    const res = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: ctrl.signal,
      cache: "no-store",
    });
    clearTimeout(t);
    if (!res.ok) {
      return {
        status: "down",
        detail: `API health returned HTTP ${res.status}. Is the Laravel server running?`,
      };
    }
    const body = (await res.json().catch(() => null)) as
      | { status?: string; database?: string }
      | null;
    if (body?.status === "ok" || body?.database === "healthy") {
      return { status: "ok" };
    }
    return {
      status: "down",
      detail: "API responded but is not ready (database/cache). Check MySQL and .env.",
    };
  } catch {
    return {
      status: "down",
      detail:
        "Cannot reach API. Start: cd apps/nursery-api && php artisan serve --host=0.0.0.0 --port=8000. Check NEXT_PUBLIC_API_BASE_URL.",
    };
  }
}
