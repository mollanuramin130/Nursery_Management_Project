"use client";

import { GreenLeafErrorView } from "@/components/error/GreenLeafErrorView";
import { newErrorReference } from "@/lib/support";
import { useMemo } from "react";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const reference = useMemo(() => newErrorReference(), []);
  return <GreenLeafErrorView reference={reference} onRetry={reset} />;
}
