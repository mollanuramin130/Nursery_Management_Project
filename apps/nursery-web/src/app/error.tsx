"use client";

import { GreenLeafErrorView } from "@/components/error/GreenLeafErrorView";
import { newErrorReference } from "@/lib/support";
import { recordClientError } from "@/lib/error-category";
import { useMemo } from "react";

export default function ErrorPage({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const reference = useMemo(() => {
    const ref = newErrorReference();
    recordClientError({ category: "unexpected", reference: ref, screen: "error.tsx" });
    return ref;
  }, []);

  return (
    <main className="site-main">
      <GreenLeafErrorView reference={reference} onRetry={reset} />
    </main>
  );
}
