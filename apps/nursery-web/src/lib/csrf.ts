/** In-memory CSRF for Customer Web BFF (readable cookie also set by server). */
let csrfToken: string | null = null;

export function getCsrfToken(): string | null {
  if (csrfToken) return csrfToken;
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|;\s*)gl_web_csrf=([^;]+)/);
  return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export function setCsrfToken(token: string | null) {
  csrfToken = token;
}

export async function ensureCsrf(): Promise<string> {
  const existing = getCsrfToken();
  if (existing && existing.length >= 16) return existing;

  const res = await fetch("/api/bff/auth/csrf", {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  const json = (await res.json()) as {
    success?: boolean;
    data?: { csrf_token?: string };
  };
  const token = json.data?.csrf_token;
  if (!token) throw new Error("Unable to initialize security token");
  setCsrfToken(token);
  return token;
}
