export const API_BASE =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

if (
  process.env.NODE_ENV === "production" &&
  /localhost|127\.0\.0\.1/i.test(API_BASE)
) {
  console.error(
    "CRITICAL: NEXT_PUBLIC_API_BASE_URL must be an HTTPS production API URL (not localhost).",
  );
}

export const STORE_NAME =
  process.env.NEXT_PUBLIC_STORE_NAME ?? "GreenLeaf Nursery";

export async function serverGet<T>(
  path: string,
  init?: RequestInit & { revalidate?: number | false },
): Promise<{ data: T; meta?: Record<string, unknown> } | null> {
  const { revalidate = 60, ...rest } = init ?? {};
  try {
    const res = await fetch(`${API_BASE}${path}`, {
      ...rest,
      headers: {
        Accept: "application/json",
        ...(rest.headers ?? {}),
      },
      ...(revalidate === false
        ? { cache: "no-store" as const }
        : { next: { revalidate } }),
    });
    const json = await res.json();
    if (!json.success) return null;
    return { data: json.data as T, meta: json.meta };
  } catch {
    return null;
  }
}
