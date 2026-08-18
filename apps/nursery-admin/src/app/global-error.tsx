"use client";

import { GreenLeafErrorView } from "@/components/error/GreenLeafErrorView";
import { newErrorReference } from "@/lib/support";
import { useMemo } from "react";

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const reference = useMemo(() => newErrorReference(), []);
  return (
    <html lang="en">
      <body>
        <GreenLeafErrorView reference={reference} onRetry={reset} />
      </body>
    </html>
  );
}
