"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { apiSend } from "@/lib/api";
import { money } from "@/lib/format";
import type { PlantFinderMatch, PlantMatchResult } from "@/lib/types";
import { useCartStore } from "@/store/cart";
import { useToastStore } from "@/store/toast";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { SafeImage } from "@/components/ui/SafeImage";

type Answers = {
  location: string;
  sunlight: string;
  watering: string;
  experience: string;
  purpose: string[];
};

const STEPS: Array<{
  key: keyof Answers;
  title: string;
  multi?: boolean;
  options: Array<{ value: string; label: string }>;
}> = [
  {
    key: "location",
    title: "Where will you keep your plant?",
    options: [
      { value: "indoor", label: "Indoor" },
      { value: "balcony", label: "Balcony" },
      { value: "terrace", label: "Terrace" },
      { value: "garden", label: "Garden" },
      { value: "office", label: "Office" },
    ],
  },
  {
    key: "sunlight",
    title: "How much sunlight does the spot get?",
    options: [
      { value: "low", label: "Low" },
      { value: "bright_indirect", label: "Bright indirect" },
      { value: "partial", label: "Partial" },
      { value: "full_sun", label: "Direct / full sun" },
    ],
  },
  {
    key: "watering",
    title: "How often can you water?",
    options: [
      { value: "rarely", label: "Rarely" },
      { value: "weekly", label: "Once a week" },
      { value: "often", label: "Several times a week" },
    ],
  },
  {
    key: "experience",
    title: "Your experience?",
    options: [
      { value: "beginner", label: "Beginner" },
      { value: "intermediate", label: "Intermediate" },
      { value: "experienced", label: "Experienced" },
    ],
  },
  {
    key: "purpose",
    title: "What matters most?",
    multi: true,
    options: [
      { value: "low_maintenance", label: "Low maintenance" },
      { value: "air_purifying", label: "Air purifying" },
      { value: "flowers", label: "Flowers" },
      { value: "decoration", label: "Decoration" },
      { value: "pet_friendly", label: "Pet-friendly" },
      { value: "edible", label: "Edible / herbs" },
    ],
  },
];

export default function FindYourPlantPage() {
  const [step, setStep] = useState(0);
  const [started, setStarted] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [match, setMatch] = useState<PlantFinderMatch | null>(null);
  const [answers, setAnswers] = useState<Answers>({
    location: "",
    sunlight: "",
    watering: "",
    experience: "",
    purpose: [],
  });
  const addItem = useCartStore((s) => s.addItem);
  const toast = useToastStore((s) => s.push);

  const current = STEPS[step];
  const showingResults = match !== null;

  const catalogHref = useMemo(() => {
    const qs = new URLSearchParams();
    qs.set("product_type", "plant");
    if (answers.location === "indoor" || answers.location === "office") {
      qs.set("indoor_outdoor", "indoor");
    } else if (answers.location === "garden" || answers.location === "terrace") {
      qs.set("indoor_outdoor", "outdoor");
    } else if (answers.location === "balcony") {
      qs.set("indoor_outdoor", "both");
    }
    if (answers.sunlight) qs.set("sunlight", answers.sunlight);
    if (answers.watering === "rarely") qs.set("water_requirement", "low");
    if (answers.watering === "weekly") qs.set("water_requirement", "medium");
    if (answers.watering === "often") qs.set("water_requirement", "high");
    if (answers.experience === "beginner") qs.set("difficulty_level", "easy");
    if (answers.experience === "intermediate") qs.set("difficulty_level", "moderate");
    if (answers.experience === "experienced") qs.set("difficulty_level", "advanced");
    qs.set("sort", "popular");
    return `/shop?${qs.toString()}`;
  }, [answers]);

  function selectOption(value: string) {
    if (!current) return;
    if (current.multi) {
      setAnswers((a) => {
        const set = new Set(a.purpose);
        if (set.has(value)) set.delete(value);
        else set.add(value);
        return { ...a, purpose: Array.from(set) };
      });
      return;
    }
    setAnswers((a) => ({ ...a, [current.key]: value }));
  }

  function canContinue() {
    if (!current) return false;
    if (current.multi) return true;
    return Boolean(answers[current.key as keyof Omit<Answers, "purpose">]);
  }

  async function runMatch() {
    setBusy(true);
    setError(null);
    try {
      const res = await apiSend<PlantFinderMatch>("post", "/plant-finder/match", {
        location: answers.location,
        sunlight: answers.sunlight,
        watering: answers.watering,
        experience: answers.experience,
        purpose: answers.purpose,
      });
      setMatch(res.data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not find matches");
    } finally {
      setBusy(false);
    }
  }

  async function next() {
    if (step < STEPS.length - 1) {
      setStep((s) => s + 1);
      return;
    }
    await runMatch();
  }

  if (!started) {
    return (
      <section className="section">
        <div className="container max-w-2xl text-center">
          <h1 className="display text-4xl text-[var(--color-primary-deep)] md:text-5xl">
            Find the perfect plant
          </h1>
          <p className="mt-3 text-[var(--color-muted)]">
            Answer a few simple questions. We match real plant attributes — not fake AI.
          </p>
          <Button className="mt-8" onClick={() => setStarted(true)}>
            Start
          </Button>
        </div>
      </section>
    );
  }

  if (showingResults) {
    const results = match.results ?? [];
    const best = results[0];
    return (
      <section className="section">
        <div className="container max-w-4xl">
          <h1 className="display text-4xl text-[var(--color-primary-deep)]">
            {results.length
              ? match.approximate
                ? "Closest matches"
                : `We found ${results.length} plants for you`
              : "We couldn't find an exact match"}
          </h1>
          <p className="mt-2 text-[var(--color-muted)]">
            {match.approximate
              ? "These are approximate matches based on your preferences."
              : "Ranked by how well each plant fits your answers."}
          </p>

          {error ? <p className="mt-4 text-[var(--color-error)]">{error}</p> : null}

          {best ? (
            <BestMatch
              row={best}
              onAdd={async () => {
                try {
                  await addItem(best.product.id, 1);
                  toast("Added to cart");
                } catch (e) {
                  toast(e instanceof Error ? e.message : "Could not add", "error");
                }
              }}
            />
          ) : null}

          {results.length > 1 ? (
            <div className="mt-10">
              <h2 className="text-xl font-bold">Other great matches</h2>
              <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                {results.slice(1).map((row) => (
                  <li
                    key={row.product.id}
                    className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-white p-4"
                  >
                    <div className="flex gap-3">
                      <div className="relative h-20 w-20 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]">
                        {row.product.thumbnail_url ? (
                          <SafeImage src={row.product.thumbnail_url} alt="" fill className="object-cover" sizes="80px" />
                        ) : null}
                      </div>
                      <div className="flex-1">
                        <p className="font-semibold">{row.product.name}</p>
                        <p className="text-sm text-[var(--color-primary)]">{row.match_score}% match</p>
                        <p className="text-sm font-medium">{money(row.product.price)}</p>
                        <Link
                          href={`/product/${row.product.slug}`}
                          className="mt-1 inline-block text-sm font-semibold text-[var(--color-primary)]"
                        >
                          View plant
                        </Link>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="mt-8 flex flex-wrap gap-3">
            <Button
              variant="outline"
              onClick={() => {
                setMatch(null);
                setStep(0);
                setAnswers({
                  location: "",
                  sunlight: "",
                  watering: "",
                  experience: "",
                  purpose: [],
                });
              }}
            >
              Adjust preferences
            </Button>
            <Link href={catalogHref}>
              <Button variant="secondary">Browse catalog filters</Button>
            </Link>
            <Link href="/shop?product_type=plant">
              <Button variant="ghost">Browse all plants</Button>
            </Link>
          </div>
        </div>
      </section>
    );
  }

  const selected =
    current.multi
      ? answers.purpose
      : [String(answers[current.key as keyof Omit<Answers, "purpose">] || "")];

  return (
    <section className="section">
      <div className="container max-w-2xl">
        <p className="text-sm font-semibold text-[var(--color-muted)]">
          Step {step + 1} of {STEPS.length}
        </p>
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-muted)]">
          <div
            className="h-full bg-[var(--color-primary)] transition-all"
            style={{ width: `${((step + 1) / STEPS.length) * 100}%` }}
          />
        </div>
        <h1 className="display mt-6 text-3xl text-[var(--color-primary-deep)] md:text-4xl">
          {current.title}
        </h1>
        {current.multi ? (
          <p className="mt-2 text-sm text-[var(--color-muted)]">Select all that apply.</p>
        ) : null}

        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          {current.options.map((opt) => {
            const active = selected.includes(opt.value);
            return (
              <button
                key={opt.value}
                type="button"
                onClick={() => selectOption(opt.value)}
                className={`rounded-[var(--radius-lg)] border px-4 py-4 text-left font-semibold transition ${
                  active
                    ? "border-[var(--color-primary)] bg-[var(--color-secondary-soft)] text-[var(--color-primary-deep)]"
                    : "border-[var(--color-border)] bg-white hover:border-[var(--color-primary)]"
                }`}
              >
                {opt.label}
              </button>
            );
          })}
        </div>

        {error ? <p className="mt-4 text-sm text-[var(--color-error)]">{error}</p> : null}

        <div className="mt-8 flex justify-between gap-3">
          <Button
            variant="ghost"
            disabled={step === 0 || busy}
            onClick={() => setStep((s) => Math.max(0, s - 1))}
          >
            Back
          </Button>
          <Button disabled={!canContinue() || busy} onClick={() => void next()}>
            {busy ? "Finding…" : step === STEPS.length - 1 ? "See matches" : "Continue"}
          </Button>
        </div>
      </div>
    </section>
  );
}

function BestMatch({
  row,
  onAdd,
}: {
  row: PlantMatchResult;
  onAdd: () => Promise<void>;
}) {
  const [busy, setBusy] = useState(false);
  return (
    <div className="mt-8 rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-white p-5 md:p-8">
      <div className="flex flex-wrap items-start gap-5">
        <div className="relative h-36 w-36 overflow-hidden rounded-[var(--radius-lg)] bg-[var(--color-surface-muted)]">
          {row.product.thumbnail_url ? (
            <SafeImage src={row.product.thumbnail_url} alt="" fill className="object-cover" sizes="144px" />
          ) : null}
        </div>
        <div className="min-w-0 flex-1">
          <Badge tone="success">Best match · {row.match_score}%</Badge>
          <h2 className="display mt-2 text-3xl text-[var(--color-primary-deep)]">
            {row.product.name}
          </h2>
          <p className="mt-1 text-lg font-semibold">{money(row.product.price)}</p>
          <ul className="mt-3 space-y-1 text-sm text-[var(--color-ink-soft)]">
            {row.match_reasons.map((r) => (
              <li key={r}>✓ {r}</li>
            ))}
          </ul>
          <div className="mt-5 flex flex-wrap gap-2">
            <Link href={`/product/${row.product.slug}`}>
              <Button variant="secondary">View plant</Button>
            </Link>
            <Button
              disabled={busy || row.product.stock_status === "out_of_stock"}
              onClick={async () => {
                setBusy(true);
                try {
                  await onAdd();
                } finally {
                  setBusy(false);
                }
              }}
            >
              {row.product.stock_status === "out_of_stock" ? "Out of stock" : "Add to cart"}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
