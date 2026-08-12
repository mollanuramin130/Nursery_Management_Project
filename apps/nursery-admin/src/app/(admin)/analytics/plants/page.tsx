"use client";

import { Suspense, useCallback } from "react";
import { AnalyticsShell } from "@/components/analytics/AnalyticsShell";
import { AnalyticsTable } from "@/components/analytics/AnalyticsWidgets";
import { fetchAnalyticsPlants } from "@/lib/api/analytics";
import { formatMoney } from "@/lib/format";
import type { AnalyticsRangeParams } from "@/lib/analytics/range";

function PlantsInner() {
  const load = useCallback((range: AnalyticsRangeParams) => fetchAnalyticsPlants(range), []);

  return (
    <AnalyticsShell
      title="Plant taxonomy sales"
      description="Revenue-qualifying order items joined to plant_profiles. Neutral dimensions — not quality judgments."
      load={load}
    >
      {({ data }) => {
        if (!data) return null;
        const note = String(data.note ?? "");
        const groups: Array<[string, string]> = [
          ["by_indoor_outdoor", "Indoor / outdoor"],
          ["by_plant_kind", "Plant kind"],
          ["by_difficulty", "Difficulty"],
          ["by_pet_safety", "Pet safety"],
          ["by_sunlight", "Sunlight"],
        ];

        return (
          <div className="space-y-6">
            <p className="text-xs text-[var(--admin-muted)]">{note}</p>
            {groups.map(([key, title]) => {
              const rows = (data[key] ?? []) as Array<Record<string, unknown>>;
              return (
                <section
                  key={key}
                  className="rounded-[var(--admin-radius-lg)] border border-[var(--admin-border)] bg-white p-4"
                >
                  <h2 className="mb-3 text-sm font-semibold">{title}</h2>
                  <AnalyticsTable
                    columns={[
                      { key: "dimension", label: "Value" },
                      { key: "orders", label: "Orders", align: "right" },
                      { key: "units", label: "Units", align: "right" },
                      {
                        key: "revenue",
                        label: "Revenue",
                        align: "right",
                        render: (row) => formatMoney(Number(row.revenue ?? 0)),
                      },
                    ]}
                    rows={rows}
                  />
                </section>
              );
            })}
          </div>
        );
      }}
    </AnalyticsShell>
  );
}

export default function PlantsAnalyticsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm">Loading…</div>}>
      <PlantsInner />
    </Suspense>
  );
}
