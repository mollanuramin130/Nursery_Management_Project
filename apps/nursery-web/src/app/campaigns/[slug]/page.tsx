import Image from "next/image";
import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ProductCard } from "@/components/product/ProductCard";
import { Badge } from "@/components/ui/Badge";
import { serverGet } from "@/lib/server-api";
import type { CampaignSummary, ProductSummary } from "@/lib/types";

type CampaignDetail = CampaignSummary & {
  description?: string | null;
  products?: ProductSummary[];
  banner_image?: string | null;
  status?: string;
  starts_at?: string | null;
  ends_at?: string | null;
};

function formatDate(iso?: string | null) {
  if (!iso) return null;
  return new Date(iso).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const res = await serverGet<CampaignDetail>(`/campaigns/${slug}`, { revalidate: 120 });
  const c = res?.data;
  if (!c) return { title: "Campaign" };
  return {
    title: `${c.title} | GreenLeaf Nursery`,
    description: c.subtitle || c.description || `Shop the ${c.title} collection.`,
  };
}

export default async function CampaignPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverGet<CampaignDetail>(`/campaigns/${slug}`, { revalidate: 60 });
  const campaign = res?.data;
  if (!campaign) notFound();

  const hero = campaign.banner_image || campaign.image_url;
  const status = campaign.status ?? "active";

  return (
    <>
      <section className="relative min-h-[46vh] overflow-hidden text-white">
        {hero ? (
          <Image src={hero} alt="" fill priority className="object-cover" sizes="100vw" />
        ) : (
          <div className="absolute inset-0 bg-[var(--color-primary-deep)]" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/35 to-black/20" />
        <div className="relative container flex min-h-[46vh] items-end pb-12">
          <div className="max-w-2xl animate-up">
            <div className="mb-3 flex flex-wrap gap-2">
              <Badge tone={status === "active" ? "success" : "neutral"}>
                {status.replaceAll("_", " ")}
              </Badge>
              {campaign.season_code ? (
                <Badge tone="brand">{campaign.season_code}</Badge>
              ) : null}
            </div>
            <h1 className="display text-[clamp(2.6rem,7vw,4.5rem)]">{campaign.title}</h1>
            {campaign.subtitle ? <p className="mt-3 text-lg text-white/90">{campaign.subtitle}</p> : null}
            <p className="mt-3 text-sm text-white/75">
              {[formatDate(campaign.starts_at), formatDate(campaign.ends_at)]
                .filter(Boolean)
                .join(" – ")}
            </p>
          </div>
        </div>
      </section>
      <section className="section">
        <div className="container">
          {campaign.description ? (
            <p className="mb-8 max-w-2xl text-lg text-[var(--color-muted)]">{campaign.description}</p>
          ) : null}

          <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-2xl font-bold text-[var(--color-primary-deep)]">
                Shop this collection
              </h2>
              <p className="text-sm text-[var(--color-muted)]">
                Normal catalog products — add to cart and checkout as usual.
              </p>
            </div>
            <Link href="/offers" className="text-sm font-semibold text-[var(--color-primary)]">
              All offers →
            </Link>
          </div>

          {(campaign.products ?? []).length ? (
            <div className="product-grid">
              {(campaign.products ?? []).map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          ) : (
            <p className="text-[var(--color-muted)]">No active products in this campaign yet.</p>
          )}
        </div>
      </section>
    </>
  );
}
