import type { Banner } from "@/lib/types";

/** Resolve API banner / offer deep links to an in-app path. */
export function bannerHref(banner?: Pick<Banner, "link_type" | "link_value"> | null): string {
  if (!banner) return "/shop";
  const type = (banner.link_type ?? "").toLowerCase();
  const value = (banner.link_value ?? "").trim();
  if (!value) return "/shop";

  switch (type) {
    case "campaign":
      return `/campaigns/${value}`;
    case "product":
      return `/product/${value}`;
    case "category":
      return `/category/${value}`;
    case "offers":
      return "/offers";
    case "find_plant":
    case "find-your-plant":
      return "/find-your-plant";
    case "catalog_filter":
      return value.startsWith("/") ? value : `/shop?${value}`;
    case "url":
      return value.startsWith("/") ? value : "/shop";
    default:
      return value.startsWith("/") ? value : `/shop?q=${encodeURIComponent(value)}`;
  }
}
