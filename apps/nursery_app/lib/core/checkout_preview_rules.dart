/// Pure checkout preview helpers (QA-CHK-001 / QA-PERF-011) — no Flutter UI deps.
abstract final class CheckoutPreviewRules {
  /// Payable total comes only from server preview — never cart merchandise.
  static double? payableTotal(Map<String, dynamic>? preview) {
    if (preview == null) return null;
    final raw = preview['grand_total'];
    if (raw is num) return raw.toDouble();
    return null;
  }

  static bool canPlace({
    required Map<String, dynamic>? preview,
    required bool busy,
    required bool submissionLocked,
  }) {
    return payableTotal(preview) != null && !busy && !submissionLocked;
  }

  /// Bump generation for each outbound preview request.
  static int nextPreviewGeneration(int current) => current + 1;

  /// Stale responses (lower generation) must not overwrite newer state.
  static bool shouldApplyPreviewResult(int responseGeneration, int latestGeneration) {
    return responseGeneration == latestGeneration;
  }
}
