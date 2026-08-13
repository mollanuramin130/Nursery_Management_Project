/**
 * Search suggestion race helpers (QA-06-001).
 * Only the latest in-flight query may commit results to UI state.
 */

export function nextSearchGeneration(current: number): number {
  return current + 1;
}

/** True when this response still matches the latest requested generation. */
export function shouldApplySearchResult(
  responseGeneration: number,
  latestGeneration: number,
): boolean {
  return responseGeneration === latestGeneration;
}
