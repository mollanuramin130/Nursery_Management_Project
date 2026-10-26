/** Readiness probe with a bounded timeout and a customer-safe failure message. */
export type ApiHealthState =
  | { status: "checking" }
  | { status: "ok" }
  | { status: "down"; detail: string };

const unavailable: ApiHealthState = {
  status: "down",
  detail: "The service is temporarily unavailable. Please try again shortly.",
};

export async function probeApiHealth(apiBaseUrl: string): Promise<ApiHealthState> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 8000);
  try {
    const res = await fetch(`${apiBaseUrl.replace(/\/$/, "")}/health/ready`, {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: controller.signal,
      cache: "no-store",
    });
    if (!res.ok) return unavailable;
    const body = await res.json() as { status?: string; database?: string };
    return body?.status === "ok" && body?.database === "healthy"
      ? { status: "ok" }
      : unavailable;
  } catch {
    return unavailable;
  } finally {
    clearTimeout(timer);
  }
}
