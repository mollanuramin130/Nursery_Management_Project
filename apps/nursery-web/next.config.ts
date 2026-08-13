import type { NextConfig } from "next";
import { loadEnvConfig } from "@next/env";

// Ensure .env.local is visible when evaluating next.config (dev origins).
loadEnvConfig(process.cwd());

/**
 * QA-33: Browser uses same-origin BFF (`/api/bff/*`); JWTs are HttpOnly.
 * CSP still limits XSS blast radius. Razorpay Checkout needs limited unsafe-*.
 */
const contentSecurityPolicy = [
  "default-src 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  "frame-ancestors 'none'",
  "form-action 'self' https://*.razorpay.com",
  "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://checkout.razorpay.com https://*.razorpay.com",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob: https:",
  "font-src 'self' data:",
  "connect-src 'self' https://*.razorpay.com https://api.qrserver.com",
  "frame-src https://api.razorpay.com https://*.razorpay.com https://checkout.razorpay.com",
].join("; ");

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "X-Frame-Options", value: "DENY" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
  { key: "Content-Security-Policy", value: contentSecurityPolicy },
];

const extraDevOrigins = (process.env.NEXT_DEV_ALLOWED_ORIGINS ?? "")
  .split(",")
  .map((s) => s.trim())
  .filter(Boolean);

/** Laravel API target for same-origin browser proxy (dev / LAN). */
const apiProxyTarget = (
  process.env.API_PROXY_TARGET ?? "http://127.0.0.1:8000/api/v1"
).replace(/\/$/, "");

const nextConfig: NextConfig = {
  // Next 16 blocks /_next JS when page host ≠ "localhost" (breaks Add to cart).
  allowedDevOrigins: ["127.0.0.1", "localhost", ...extraDevOrigins],
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "images.unsplash.com" },
      { protocol: "https", hostname: "example.com" },
    ],
  },
  async rewrites() {
    // Browser → same origin /backend-api → Laravel.
    // Avoids Chrome blocking 127.0.0.1 API calls from http://LAN_IP:3000 pages.
    return [
      {
        source: "/backend-api/:path*",
        destination: `${apiProxyTarget}/:path*`,
      },
    ];
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: securityHeaders,
      },
    ];
  },
};

export default nextConfig;
