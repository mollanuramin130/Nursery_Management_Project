export function FormSection({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4">
      <div className="mb-3">
        <h2 className="text-sm font-semibold text-[var(--admin-ink)]">{title}</h2>
        {description ? (
          <p className="mt-1 text-xs text-[var(--admin-muted)]">{description}</p>
        ) : null}
      </div>
      {children}
    </section>
  );
}
