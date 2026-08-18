"use client";

import { GreenLeafErrorView } from "@/components/error/GreenLeafErrorView";
import { newErrorReference } from "@/lib/support";
import { recordClientError } from "@/lib/error-category";
import { useMemo } from "react";

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const reference = useMemo(() => {
    const ref = newErrorReference();
    recordClientError({ category: "unexpected", reference: ref, screen: "global-error" });
    return ref;
  }, []);

  return (
    <html lang="en">
      <body>
        <GreenLeafErrorView reference={reference} onRetry={reset} />
      </body>
    </html>
  );
}
