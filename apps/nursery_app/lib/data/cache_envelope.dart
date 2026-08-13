import 'package:nursery_app/data/mock_data_mode.dart';

/// QA-38 — persisted cache metadata. Mock never overwrites API envelopes.
class CacheEnvelope {
  const CacheEnvelope({
    required this.data,
    required this.source,
    required this.cachedAt,
    this.version = 1,
  });

  final dynamic data;
  final DataSourceKind source;
  final DateTime cachedAt;
  final int version;

  static const freshWindow = Duration(minutes: 5);

  bool isFresh([Duration window = freshWindow]) =>
      DateTime.now().difference(cachedAt) <= window;

  /// Written from a successful API response (or legacy migrated API payload).
  bool get isApiOrigin =>
      source == DataSourceKind.remote ||
      source == DataSourceKind.cache ||
      source == DataSourceKind.staleCache;

  DataSourceKind get readSource =>
      isApiOrigin
          ? (isFresh() ? DataSourceKind.cache : DataSourceKind.staleCache)
          : source;

  Map<String, dynamic> toJson() => {
        'cachedAt': cachedAt.toIso8601String(),
        'source': source == DataSourceKind.staleCache
            ? DataSourceKind.remote.name
            : source.name,
        'version': version,
        'data': data,
      };

  static CacheEnvelope? tryParse(dynamic raw) {
    if (raw is! Map) return null;
    final map = Map<String, dynamic>.from(raw);
    if (!map.containsKey('cachedAt') || !map.containsKey('data')) {
      return CacheEnvelope(
        data: map,
        source: DataSourceKind.remote,
        cachedAt: DateTime.fromMillisecondsSinceEpoch(0),
        version: 0,
      );
    }
    final srcName = map['source']?.toString() ?? 'remote';
    var source = DataSourceKind.values.firstWhere(
      (e) => e.name == srcName,
      orElse: () => DataSourceKind.remote,
    );
    if (source == DataSourceKind.cache || source == DataSourceKind.staleCache) {
      source = DataSourceKind.remote;
    }
    final at = DateTime.tryParse(map['cachedAt']?.toString() ?? '') ??
        DateTime.fromMillisecondsSinceEpoch(0);
    return CacheEnvelope(
      data: map['data'],
      source: source,
      cachedAt: at,
      version: (map['version'] as num?)?.toInt() ?? 1,
    );
  }

  /// Mock must never replace API-origin data.
  static bool mayWrite({
    required CacheEnvelope? existing,
    required DataSourceKind incoming,
  }) {
    if (existing == null) return true;
    if (incoming == DataSourceKind.remote) return true;
    if (incoming == DataSourceKind.mock && existing.isApiOrigin) return false;
    if (incoming == DataSourceKind.mock) return true;
    return true;
  }
}
