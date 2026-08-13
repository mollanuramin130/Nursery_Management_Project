/**
 * Checkout preview race helpers (QA-PERF-011).
 * Same generation pattern as search suggestions (QA-06-001).
 */
export function nextPreviewGeneration(current: number): number {
  return current + 1;
}

/** True when this response still matches the latest requested generation. */
export function shouldApplyPreviewResult(
  responseGeneration: number,
  latestGeneration: number,
): boolean {
  return responseGeneration === latestGeneration;
}
