/// QA-43 — attach duplicate refresh calls to the in-flight Future.
class RefreshCoalescer {
  Future<void>? _inFlight;

  bool get isRunning => _inFlight != null;

  Future<void> run(Future<void> Function() work) {
    final existing = _inFlight;
    if (existing != null) return existing;
    late final Future<void> created;
    created = Future<void>.sync(work).whenComplete(() {
      if (identical(_inFlight, created)) {
        _inFlight = null;
      }
    });
    _inFlight = created;
    return created;
  }
}
