/// Single-flight token refresh coordinator (QA-12).
///
/// When many API calls hit 401 at once, only one refresh runs; others await it.
class TokenRefreshCoordinator {
  Future<bool>? _inFlight;

  /// Returns whether a refresh is currently in progress.
  bool get isRefreshing => _inFlight != null;

  Future<bool> run(Future<bool> Function() refresh) {
    final existing = _inFlight;
    if (existing != null) return existing;

    final started = refresh().whenComplete(() {
      _inFlight = null;
    });
    _inFlight = started;
    return started;
  }

  /// Clears in-flight state (unit tests only).
  void debugReset() {
    _inFlight = null;
  }
}
