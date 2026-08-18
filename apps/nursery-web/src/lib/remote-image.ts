/** Hosts that 429 / 403 Next.js `/_next/image` (bot UA). Load in the browser instead. */
const BYPASS_OPTIMIZER_HOSTS = new Set([
  "upload.wikimedia.org",
  "commons.wikimedia.org",
]);

export function shouldBypassNextImageOptimizer(src?: string | null): boolean {
  if (!src || typeof src !== "string") return false;
  try {
    return BYPASS_OPTIMIZER_HOSTS.has(new URL(src).hostname);
  } catch {
    return false;
  }
}
