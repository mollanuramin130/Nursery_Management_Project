/**
 * Sanitize post-login return paths (open-redirect safe).
 * Mirrors Customer Web sanitizeNext.
 */
export function sanitizeAdminNext(raw: string | null | undefined): string {
  if (!raw) return "/dashboard";
  let path = raw.trim();
  try {
    path = decodeURIComponent(path);
  } catch {
    return "/dashboard";
  }
  if (!path.startsWith("/") || path.startsWith("//")) return "/dashboard";
  const pathname = path.split("?")[0] ?? path;
  if (pathname === "/login") return "/dashboard";
  return path;
}
