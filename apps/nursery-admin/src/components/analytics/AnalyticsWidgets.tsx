"use client";

import { formatMoney } from "@/lib/format";

export function PctBadge({ value }: { value: number | null | undefined }) {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return <span className="text-xs text-[var(--admin-muted)]">vs prior: n/a</span>;
  }
  const positive = value > 0;
  const negative = value < 0;
  return (
    <span
      className={
        positive
          ? "text-xs font-medium text-emerald-700"
          : negative
            ? "text-xs font-medium text-red-700"
            : "text-xs text-[var(--admin-muted)]"
      }
    >
      {positive ? "+" : ""}
      {value.toFixed(1)}% vs prior
    </span>
  );
}

export function KpiCard({
  label,
  value,
  changePct,
  money,
  hint,
}: {
  label: string;
  value: number | string;
  changePct?: number | null;
  money?: boolean;
  hint?: string;
}) {
  const display =
    typeof value === "number" ? (money ? formatMoney(value) : value.toLocaleString("en-IN")) : value;
  return (
    <div className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3 shadow-[var(--admin-shadow)]">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[var(--admin-muted)]">
        {label}
      </div>
      <div className="mt-1 text-xl font-semibold tabular-nums text-[var(--admin-ink)]">{display}</div>
      {changePct !== undefined ? (
        <div className="mt-1">
          <PctBadge value={changePct} />
        </div>
      ) : null}
      {hint ? <div className="mt-1 text-[11px] text-[var(--admin-muted)]">{hint}</div> : null}
    </div>
  );
}

export function TrendBars({
  points,
  valueKey = "revenue",
  labelKey = "bucket",
}: {
  points: Array<Record<string, unknown>>;
  valueKey?: string;
  labelKey?: string;
}) {
  if (!points.length) {
    return <p className="text-sm text-[var(--admin-muted)]">No data for this period.</p>;
  }
  const max = Math.max(
    ...points.map((p) => Number(p[valueKey] ?? 0)),
    1,
  );
  return (
    <div className="space-y-2" role="img" aria-label="Trend chart">
      {points.map((p) => {
        const v = Number(p[valueKey] ?? 0);
        const pct = Math.round((v / max) * 100);
        return (
          <div key={String(p[labelKey])} className="grid grid-cols-[88px_1fr_auto] items-center gap-2 text-xs">
            <span className="truncate text-[var(--admin-muted)]">{String(p[labelKey])}</span>
            <div className="h-2 overflow-hidden rounded bg-[var(--admin-surface-muted,#f3f4f6)]">
              <div className="h-full bg-[var(--admin-primary,#166534)]" style={{ width: `${pct}%` }} />
            </div>
            <span className="tabular-nums font-medium">{formatMoney(v)}</span>
          </div>
        );
      })}
      <table className="sr-only">
        <thead>
          <tr>
            <th>Period</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
          {points.map((p) => (
            <tr key={`a-${String(p[labelKey])}`}>
              <td>{String(p[labelKey])}</td>
              <td>{String(p[valueKey])}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function AnalyticsTable({
  columns,
  rows,
}: {
  columns: { key: string; label: string; align?: "left" | "right"; render?: (row: Record<string, unknown>) => React.ReactNode }[];
  rows: Array<Record<string, unknown>>;
}) {
  if (!rows.length) {
    return <p className="text-sm text-[var(--admin-muted)]">No rows.</p>;
  }
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-left text-sm">
        <thead className="sticky top-0 border-b border-[var(--admin-border)] bg-white text-[11px] uppercase text-[var(--admin-muted)]">
          <tr>
            {columns.map((c) => (
              <th
                key={c.key}
                className={`px-2 py-2 font-medium ${c.align === "right" ? "text-right" : ""}`}
              >
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i} className="border-b border-[var(--admin-border)]/60">
              {columns.map((c) => (
                <td
                  key={c.key}
                  className={`px-2 py-2 tabular-nums ${c.align === "right" ? "text-right" : ""}`}
                >
                  {c.render ? c.render(row) : String(row[c.key] ?? "—")}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
