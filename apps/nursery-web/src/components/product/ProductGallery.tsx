"use client";

import Image from "next/image";
import { useCallback, useEffect, useState } from "react";
import { cn } from "@/lib/cn";

type Img = { id?: number; url: string; alt?: string | null; is_primary?: boolean };

export function ProductGallery({
  images,
  name,
  fallback,
}: {
  images?: Img[] | null;
  name: string;
  fallback?: string | null;
}) {
  const list =
    images && images.length
      ? images
      : fallback
        ? [{ url: fallback, alt: name, is_primary: true }]
        : [];
  const [active, setActive] = useState(0);
  const [lightbox, setLightbox] = useState(false);
  const current = list[active] ?? list[0];

  const close = useCallback(() => setLightbox(false), []);

  useEffect(() => {
    if (!lightbox) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") close();
      if (e.key === "ArrowRight") setActive((i) => Math.min(list.length - 1, i + 1));
      if (e.key === "ArrowLeft") setActive((i) => Math.max(0, i - 1));
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [lightbox, close, list.length]);

  if (!current) {
    return (
      <div className="aspect-[4/5] rounded-[var(--radius-xl)] bg-[var(--color-surface-muted)]" />
    );
  }

  return (
    <div>
      <button
        type="button"
        className="relative aspect-[4/5] w-full overflow-hidden rounded-[var(--radius-xl)] bg-[var(--color-surface-muted)]"
        onClick={() => setLightbox(true)}
        aria-label={`View larger image of ${name}`}
      >
        <Image
          key={current.url}
          src={current.url}
          alt={current.alt || name}
          fill
          priority
          className="object-cover transition duration-300 hover:scale-[1.02]"
          sizes="(max-width: 1024px) 100vw, 50vw"
        />
        <span className="absolute bottom-3 right-3 rounded-full bg-black/55 px-3 py-1 text-xs font-semibold text-white">
          Tap to enlarge
        </span>
      </button>
      {list.length > 1 ? (
        <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
          {list.map((img, i) => (
            <button
              key={img.id ?? img.url}
              type="button"
              onClick={() => setActive(i)}
              aria-label={`View image ${i + 1}`}
              aria-current={i === active}
              className={cn(
                "relative h-16 w-16 shrink-0 overflow-hidden rounded-[var(--radius-md)] border-2",
                i === active
                  ? "border-[var(--color-primary-deep)]"
                  : "border-transparent opacity-80 hover:opacity-100",
              )}
            >
              <Image src={img.url} alt="" fill className="object-cover" sizes="64px" />
            </button>
          ))}
        </div>
      ) : null}

      {lightbox ? (
        <div
          className="fixed inset-0 z-[80] flex items-center justify-center bg-black/85 p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Product image viewer"
          onClick={close}
        >
          <button
            type="button"
            className="absolute right-4 top-4 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold text-white"
            onClick={close}
          >
            Close
          </button>
          <div
            className="relative h-[min(85vh,900px)] w-full max-w-4xl"
            onClick={(e) => e.stopPropagation()}
          >
            <Image
              src={(list[active] ?? current).url}
              alt={(list[active] ?? current).alt || name}
              fill
              className="object-contain"
              sizes="100vw"
              priority
            />
          </div>
        </div>
      ) : null}
    </div>
  );
}
