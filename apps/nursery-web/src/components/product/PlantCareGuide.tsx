"use client";

import { useEffect, useState } from "react";
import { catalogService } from "@/lib/services";
import { Skeleton } from "@/components/ui/Skeleton";

type CareData = {
  sunlight_label?: string | null;
  water_label?: string | null;
  soil_type?: string | null;
  temperature_min_c?: number | null;
  temperature_max_c?: number | null;
  difficulty_level?: string | null;
  growing_instructions?: string | null;
  planting_instructions?: string | null;
  pruning_instructions?: string | null;
  fertilization_instructions?: string | null;
  pest_disease_info?: string | null;
  pet_safety?: string | null;
  toxicity_info?: string | null;
};

const SECTIONS: Array<{ key: keyof CareData; title: string }> = [
  { key: "growing_instructions", title: "Growing" },
  { key: "planting_instructions", title: "Planting" },
  { key: "fertilization_instructions", title: "Fertilization" },
  { key: "pruning_instructions", title: "Pruning" },
  { key: "pest_disease_info", title: "Pests & diseases" },
  { key: "toxicity_info", title: "Toxicity notes" },
];

export function PlantCareGuide({ slug }: { slug: string }) {
  const [care, setCare] = useState<CareData | null>(null);
  const [loading, setLoading] = useState(true);
  const [openKey, setOpenKey] = useState<string | null>("growing_instructions");

  useEffect(() => {
    let cancelled = false;
    queueMicrotask(() => setLoading(true));
    void catalogService
      .plantCare(slug)
      .then((res) => {
        if (!cancelled) setCare((res.data as CareData) ?? null);
      })
      .catch(() => {
        if (!cancelled) setCare(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (loading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (!care || !Object.keys(care).length) return null;

  const temp =
    care.temperature_min_c != null && care.temperature_max_c != null
      ? `${care.temperature_min_c}–${care.temperature_max_c}°C`
      : null;

  const cards = [
    { label: "Sunlight", value: care.sunlight_label },
    { label: "Water", value: care.water_label },
    { label: "Soil", value: care.soil_type },
    { label: "Temperature", value: temp },
    { label: "Difficulty", value: care.difficulty_level?.replaceAll("_", " ") },
    { label: "Pet safety", value: care.pet_safety?.replaceAll("_", " ") },
  ].filter((c) => c.value);

  const sections = SECTIONS.filter((s) => care[s.key]);

  return (
    <div>
      <h2 className="display text-3xl text-[var(--color-primary-deep)]">Care guide</h2>
      <p className="mt-2 text-[var(--color-muted)]">
        Practical guidance so your plant settles in well after delivery.
      </p>

      {cards.length ? (
        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {cards.map((c) => (
            <div
              key={c.label}
              className="rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-primary-soft)] px-3.5 py-3"
            >
              <p className="text-xs font-semibold uppercase tracking-wide text-[var(--color-muted)]">
                {c.label}
              </p>
              <p className="mt-1 font-semibold capitalize text-[var(--color-primary-deep)]">
                {c.value}
              </p>
            </div>
          ))}
        </div>
      ) : null}

      {sections.length ? (
        <div className="mt-6 space-y-2">
          {sections.map((s) => {
            const open = openKey === s.key;
            return (
              <div
                key={s.key}
                className="overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)] bg-white"
              >
                <button
                  type="button"
                  className="flex min-h-12 w-full items-center justify-between px-4 text-left text-sm font-semibold"
                  aria-expanded={open}
                  onClick={() => setOpenKey(open ? null : s.key)}
                >
                  {s.title}
                  <span aria-hidden>{open ? "−" : "+"}</span>
                </button>
                {open ? (
                  <p className="border-t border-[var(--color-border)] px-4 py-3 text-sm leading-relaxed text-[var(--color-ink-soft)]">
                    {String(care[s.key])}
                  </p>
                ) : null}
              </div>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
