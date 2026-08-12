import { discountPercent } from "@/lib/cn";
import { money } from "@/lib/format";

export function ProductPrice({
  price,
  compareAt,
  currency = "INR",
  size = "md",
}: {
  price: number;
  compareAt?: number | null;
  currency?: string;
  size?: "sm" | "md" | "lg";
}) {
  const off = discountPercent(price, compareAt);
  const priceClass =
    size === "lg" ? "text-2xl" : size === "sm" ? "text-sm" : "text-base";

  return (
    <div className="flex flex-wrap items-baseline gap-2">
      <span className={`font-bold tracking-tight text-[var(--color-primary-deep)] ${priceClass}`}>
        {money(price, currency)}
      </span>
      {compareAt && compareAt > price ? (
        <span className="text-sm text-[var(--color-muted)] line-through">
          {money(compareAt, currency)}
        </span>
      ) : null}
      {off ? (
        <span className="text-xs font-bold text-[var(--color-sale)]">{off}% OFF</span>
      ) : null}
    </div>
  );
}
