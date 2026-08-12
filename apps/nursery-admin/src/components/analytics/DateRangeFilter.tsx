"use client";

import { ANALYTICS_PRESETS, type AnalyticsPreset, type AnalyticsRangeParams } from "@/lib/analytics/range";

export function DateRangeFilter({
  value,
  onChange,
}: {
  value: AnalyticsRangeParams;
  onChange: (next: AnalyticsRangeParams) => void;
}) {
  return (
    <div className="flex flex-wrap items-end gap-3 rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-3">
      <label className="text-xs font-medium text-[var(--admin-muted)]">
        Period
        <select
          className="mt-1 block min-w-[160px] rounded border border-[var(--admin-border)] bg-white px-2 py-1.5 text-sm text-[var(--admin-ink)]"
          value={value.preset}
          onChange={(e) => {
            const preset = e.target.value as AnalyticsPreset;
            onChange(
              preset === "custom"
                ? { ...value, preset }
                : { preset, from: undefined, to: undefined },
            );
          }}
        >
          {ANALYTICS_PRESETS.map((p) => (
            <option key={p.value} value={p.value}>
              {p.label}
            </option>
          ))}
        </select>
      </label>
      {value.preset === "custom" ? (
        <>
          <label className="text-xs font-medium text-[var(--admin-muted)]">
            From
            <input
              type="date"
              className="mt-1 block rounded border border-[var(--admin-border)] px-2 py-1.5 text-sm"
              value={value.from ?? ""}
              onChange={(e) => onChange({ ...value, from: e.target.value, preset: "custom" })}
            />
          </label>
          <label className="text-xs font-medium text-[var(--admin-muted)]">
            To
            <input
              type="date"
              className="mt-1 block rounded border border-[var(--admin-border)] px-2 py-1.5 text-sm"
              value={value.to ?? ""}
              onChange={(e) => onChange({ ...value, to: e.target.value, preset: "custom" })}
            />
          </label>
        </>
      ) : null}
    </div>
  );
}
