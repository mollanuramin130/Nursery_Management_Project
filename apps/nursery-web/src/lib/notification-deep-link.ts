/**
 * QA-22 — notification deep-link helpers (Customer Web).
 * Prefer server `data.route` when it is a safe relative path.
 */

export function notificationHref(data: unknown, audience: "customer" | "admin" = "customer"): string | null {
  if (!data || typeof data !== "object") return null;
  const d = data as Record<string, unknown>;
  const route = d.route;
  if (typeof route === "string" && /^\/[A-Za-z0-9/_-]*$/.test(route)) {
    // QA-36-005: customer app has /account/returns/*, not bare /returns/*.
    if (audience === "customer" && /^\/returns(\/|$)/.test(route)) {
      return route.replace(/^\/returns/, "/account/returns");
    }
    return route;
  }
  if (d.order_id != null && String(d.order_id).match(/^\d+$/)) {
    return audience === "admin" ? `/orders/${d.order_id}` : `/account/orders/${d.order_id}`;
  }
  if (d.return_id != null && String(d.return_id).match(/^\d+$/)) {
    return audience === "admin" ? `/returns/${d.return_id}` : `/account/returns/${d.return_id}`;
  }
  if (typeof d.product_slug === "string" && /^[a-z0-9-]+$/i.test(d.product_slug)) {
    return `/product/${d.product_slug}`;
  }
  if (typeof d.campaign_slug === "string" && /^[a-z0-9-]+$/i.test(d.campaign_slug)) {
    return `/campaigns/${d.campaign_slug}`;
  }
  return null;
}
