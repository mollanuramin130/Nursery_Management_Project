import type { MetadataRoute } from "next";

/**
 * Staging must not be indexed. Production indexes only when SITE_ENV=production.
 * Local/dev defaults to noindex.
 */
export default function robots(): MetadataRoute.Robots {
  const siteEnv = (process.env.NEXT_PUBLIC_SITE_ENV ?? "local").toLowerCase();
  const allowIndex = siteEnv === "production";

  if (!allowIndex) {
    return {
      rules: {
        userAgent: "*",
        disallow: "/",
      },
    };
  }

  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: ["/account/", "/checkout", "/cart", "/login"],
    },
    sitemap: undefined,
  };
}
