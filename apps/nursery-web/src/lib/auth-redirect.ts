/**
 * Sanitize post-login return paths (open-redirect safe).
 * Mirrors Flutter AuthNavigation.sanitizeRedirect intent.
 */
export function sanitizeNext(raw: string | null | undefined): string {
  if (!raw) return "/account";
  let path = raw.trim();
  try {
    path = decodeURIComponent(path);
  } catch {
    return "/account";
  }
  if (!path.startsWith("/") || path.startsWith("//")) return "/account";
  const pathname = path.split("?")[0] ?? path;
  if (["/login", "/register", "/forgot-password"].includes(pathname)) {
    return "/account";
  }
  return path;
}

export function loginHref(next?: string | null): string {
  const safe = next ? sanitizeNext(next) : null;
  if (!safe || safe === "/account") return "/login";
  return `/login?next=${encodeURIComponent(safe)}`;
}
