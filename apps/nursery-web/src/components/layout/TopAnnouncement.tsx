"use client";

import { useEffect, useState } from "react";
import { apiGet } from "@/lib/api";
import { cn } from "@/lib/cn";

export function TopAnnouncement() {
  const [index, setIndex] = useState(0);
  const [hidden, setHidden] = useState(false);
  const [threshold, setThreshold] = useState(999);

  useEffect(() => {
    queueMicrotask(() => {
      if (sessionStorage.getItem("gl_announce_dismissed") === "1") setHidden(true);
    });
    void apiGet<{ commerce?: { free_delivery_threshold?: number } }>("/app/config")
      .then((res) => {
        const t = res.data?.commerce?.free_delivery_threshold;
        if (typeof t === "number" && t > 0) setThreshold(Math.round(t));
      })
      .catch(() => {
        /* keep default */
      });
  }, []);

  const messages = [
    `Free delivery on orders above ₹${threshold}`,
    "Fresh plants packed with care — delivered to your doorstep",
    "Monsoon picks are live — explore seasonal collections",
  ];

  useEffect(() => {
    if (hidden) return;
    const id = window.setInterval(() => {
      setIndex((i) => (i + 1) % messages.length);
    }, 4500);
    return () => window.clearInterval(id);
  }, [hidden, messages.length]);

  if (hidden) return null;

  return (
    <div className="relative z-50 bg-[var(--color-primary-deep)] text-white">
      <div className="container flex min-h-10 items-center justify-center gap-3 py-2 text-center text-xs font-medium tracking-wide sm:text-sm">
        <p key={`${index}-${threshold}`} className="animate-fade px-8">
          {messages[index]}
        </p>
        <button
          type="button"
          aria-label="Dismiss announcement"
          className={cn(
            "absolute right-3 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-white/80 hover:text-white",
          )}
          onClick={() => {
            sessionStorage.setItem("gl_announce_dismissed", "1");
            setHidden(true);
          }}
        >
          ×
        </button>
      </div>
    </div>
  );
}
