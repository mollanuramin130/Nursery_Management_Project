import 'package:flutter/foundation.dart';

/// QA-37/38 — how catalog/cart reads choose remote vs mock.
enum MockDataMode {
  /// Cache-first UI, then API; on failure cache → mock.
  auto,

  /// Never use mock (errors surface).
  onlineOnly,

  /// Always serve bundled mock JSON (DEBUG/QA).
  mockOnly,

  /// Force transport failures so AUTO fallback is exercised.
  offlineSimulation,
}

/// DEBUG-only request simulation (does not change physical radios).
enum NetworkSimulation {
  off,
  offline,
  slow,
  apiError,
  timeout,
  /// Alias clarity for QA panel — same as clearing sim after offline.
  reconnect,
}

/// Result provenance — API always wins over cache/mock.
enum DataSourceKind {
  remote,
  cache,
  staleCache,
  mock,
  localMutation,
}

class ResolvedResult<T> {
  const ResolvedResult({
    required this.data,
    required this.source,
    this.stale = false,
    this.cachedAt,
    this.updating = false,
  });

  final T data;
  final DataSourceKind source;
  final bool stale;
  final DateTime? cachedAt;

  /// True when cache/mock is shown while a background API refresh runs.
  final bool updating;

  bool get isLocal =>
      source == DataSourceKind.mock ||
      source == DataSourceKind.cache ||
      source == DataSourceKind.staleCache ||
      source == DataSourceKind.localMutation;

  ResolvedResult<T> copyWith({
    T? data,
    DataSourceKind? source,
    bool? stale,
    DateTime? cachedAt,
    bool? updating,
  }) {
    return ResolvedResult(
      data: data ?? this.data,
      source: source ?? this.source,
      stale: stale ?? this.stale,
      cachedAt: cachedAt ?? this.cachedAt,
      updating: updating ?? this.updating,
    );
  }
}

MockDataMode defaultMockDataMode() => MockDataMode.auto;

bool get allowDebugNetworkPanel => kDebugMode;
