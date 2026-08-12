import Link from "next/link";

export type Crumb = { label: string; href?: string };

export function Breadcrumbs({ items }: { items: Crumb[] }) {
  if (!items.length) return null;
  return (
    <nav aria-label="Breadcrumb" className="mb-3 text-xs text-[var(--admin-muted)]">
      <ol className="flex flex-wrap items-center gap-1.5">
        {items.map((item, index) => {
          const last = index === items.length - 1;
          return (
            <li key={`${item.label}-${index}`} className="flex items-center gap-1.5">
              {index > 0 ? <span>/</span> : null}
              {item.href && !last ? (
                <Link href={item.href} className="hover:text-[var(--admin-ink)]">
                  {item.label}
                </Link>
              ) : (
                <span className={last ? "font-medium text-[var(--admin-ink-soft)]" : undefined}>
                  {item.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
