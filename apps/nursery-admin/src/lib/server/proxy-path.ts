/** Validate decoded catch-all segments before constructing an upstream URL. */
export function proxyPath(parts: string[]): string | null {
  if (!parts.length || parts.some((part) =>
    !part || part === "." || part === ".." || /[\\/%?#\x00-\x20\x7f]/.test(part)
  )) return null;
  return `/${parts.map(encodeURIComponent).join("/")}`;
}

/** All session-token issuers must use the dedicated HttpOnly-cookie handlers. */
export function requiresAuthHandler(path: string): boolean {
  return ["/auth/login", "/auth/login/otp", "/auth/register", "/auth/refresh", "/auth/logout"].includes(path);
}
