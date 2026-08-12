export type AnalyticsPreset =
  | "today"
  | "yesterday"
  | "last_7_days"
  | "last_30_days"
  | "this_month"
  | "last_month"
  | "this_year"
  | "custom";

export const ANALYTICS_PRESETS: { value: AnalyticsPreset; label: string }[] = [
  { value: "today", label: "Today" },
  { value: "yesterday", label: "Yesterday" },
  { value: "last_7_days", label: "Last 7 days" },
  { value: "last_30_days", label: "Last 30 days" },
  { value: "this_month", label: "This month" },
  { value: "last_month", label: "Last month" },
  { value: "this_year", label: "This year" },
  { value: "custom", label: "Custom range" },
];

export type AnalyticsRangeParams = {
  preset: AnalyticsPreset;
  from?: string;
  to?: string;
};

export function rangeFromSearchParams(sp: URLSearchParams): AnalyticsRangeParams {
  const preset = (sp.get("preset") as AnalyticsPreset) || "last_30_days";
  const from = sp.get("from") || undefined;
  const to = sp.get("to") || undefined;
  if (from || to) {
    return { preset: preset === "custom" || from || to ? "custom" : preset, from, to };
  }
  return { preset: ANALYTICS_PRESETS.some((p) => p.value === preset) ? preset : "last_30_days" };
}

export function rangeToQuery(range: AnalyticsRangeParams): Record<string, string> {
  const q: Record<string, string> = { preset: range.preset };
  if (range.preset === "custom") {
    if (range.from) q.from = range.from;
    if (range.to) q.to = range.to;
  }
  return q;
}
