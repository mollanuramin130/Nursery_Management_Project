"use client";

import Image, { type ImageProps } from "next/image";
import { useState } from "react";
import { cn } from "@/lib/cn";

type Props = Omit<ImageProps, "onError" | "src"> & {
  src?: string | null;
  /** Soft fallback when remote image 404s / fails (QA-06-002). */
  fallbackClassName?: string;
};

/**
 * next/image wrapper that degrades to a muted placeholder when the upstream
 * CDN returns 404 (common with stale Unsplash seed URLs).
 */
export function SafeImage({
  src,
  alt,
  className,
  fallbackClassName,
  ...rest
}: Props) {
  const [failed, setFailed] = useState(false);
  const usable = typeof src === "string" && src.trim().length > 0 && !failed;

  if (!usable) {
    return (
      <div
        className={cn(
          "flex h-full w-full items-center justify-center bg-[var(--color-surface-muted)] text-[var(--color-muted)]",
          fallbackClassName,
          className,
        )}
        role="img"
        aria-label={alt || "Image unavailable"}
      >
        <span className="text-xs font-semibold uppercase tracking-wide">GreenLeaf</span>
      </div>
    );
  }

  return (
    <Image
      {...rest}
      src={src}
      alt={alt}
      className={className}
      onError={() => setFailed(true)}
    />
  );
}
