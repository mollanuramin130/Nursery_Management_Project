export function ProductRating({
  avg,
  count,
}: {
  avg?: number;
  count?: number;
}) {
  if (!avg && !count) return null;
  return (
    <p className="text-sm text-[var(--color-ink-soft)]">
      <span aria-hidden>★</span>{" "}
      <span className="font-semibold">{(avg ?? 0).toFixed(1)}</span>
      {typeof count === "number" ? (
        <span className="text-[var(--color-muted)]"> ({count})</span>
      ) : null}
    </p>
  );
}
