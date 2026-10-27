import { BRAND } from "@/lib/brand";
import type { Metadata } from "next";
import { Fraunces, Figtree } from "next/font/google";
import { Providers } from "@/components/Providers";
import { TopAnnouncement } from "@/components/layout/TopAnnouncement";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { CategoryNav } from "@/components/layout/CategoryNav";
import { MobileBottomNav } from "@/components/layout/MobileBottomNav";
import "./globals.css";

const display = Fraunces({
  variable: "--font-display",
  subsets: ["latin"],
});

const body = Figtree({
  variable: "--font-body",
  subsets: ["latin"],
});

const storeName = BRAND.name;
const siteEnv = (process.env.NEXT_PUBLIC_SITE_ENV ?? "local").toLowerCase();
const allowIndex = siteEnv === "production";

export const metadata: Metadata = {
  title: {
    default: BRAND.title,
    template: `%s · ${storeName}`,
  },
  description: BRAND.description,
  metadataBase: new URL(BRAND.website),
  applicationName: BRAND.name,
  openGraph: {
    type: "website",
    siteName: BRAND.name,
    title: BRAND.title,
    description: BRAND.description,
  },
  twitter: { card: "summary", title: BRAND.title, description: BRAND.description },
  robots: allowIndex
    ? { index: true, follow: true }
    : { index: false, follow: false },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className={`${display.variable} ${body.variable} h-full`}>
      <body className="min-h-full antialiased" suppressHydrationWarning>
        <Providers>
          <div className="site-shell">
            <TopAnnouncement />
            <SiteHeader />
            <CategoryNav />
            <main className="site-main">{children}</main>
            <SiteFooter />
            <MobileBottomNav />
          </div>
        </Providers>
      </body>
    </html>
  );
}
