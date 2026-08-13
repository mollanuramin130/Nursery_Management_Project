/**
 * Browser API base URL.
 * QA-33: always same-origin BFF proxy so JWTs remain HttpOnly cookies.
 * Laravel upstream is configured server-side via API_PROXY_TARGET.
 */
export function resolveBrowserApiBaseUrl(): string {
  return "/api/bff/proxy";
}
