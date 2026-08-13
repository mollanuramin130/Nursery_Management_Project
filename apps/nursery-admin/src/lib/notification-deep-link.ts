/**
 * Admin notification deep links.
 */
export function adminNotificationHref(data: unknown): string | null {
  if (!data || typeof data !== "object") return null;
  const d = data as Record<string, unknown>;
  const route = d.route;
  if (typeof route === "string" && /^\/[A-Za-z0-9/_-]*$/.test(route)) {
    return route.startsWith("/admin") ? route : route;
  }
  if (d.order_id != null && String(d.order_id).match(/^\d+$/)) {
    return `/orders/${d.order_id}`;
  }
  if (d.return_id != null && String(d.return_id).match(/^\d+$/)) {
    return `/returns/${d.return_id}`;
  }
  return null;
}
