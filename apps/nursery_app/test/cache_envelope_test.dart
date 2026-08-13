import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/data/cache_envelope.dart';
import 'package:nursery_app/data/mock_data_mode.dart';

void main() {
  group('CacheEnvelope QA-38 priority', () {
    test('mock cannot overwrite API-origin envelope', () {
      final api = CacheEnvelope(
        data: {'price': 219},
        source: DataSourceKind.remote,
        cachedAt: DateTime.now(),
      );
      expect(
        CacheEnvelope.mayWrite(
          existing: api,
          incoming: DataSourceKind.mock,
        ),
        isFalse,
      );
    });

    test('API can overwrite mock', () {
      final mock = CacheEnvelope(
        data: {'price': 199},
        source: DataSourceKind.mock,
        cachedAt: DateTime.now(),
      );
      expect(
        CacheEnvelope.mayWrite(
          existing: mock,
          incoming: DataSourceKind.remote,
        ),
        isTrue,
      );
    });

    test('round-trip JSON preserves data', () {
      final env = CacheEnvelope(
        data: {
          'featured_products': [
            {'id': 101, 'name': 'Money Plant', 'slug': 'money-plant', 'price': 299},
          ],
        },
        source: DataSourceKind.remote,
        cachedAt: DateTime.parse('2026-08-13T12:00:00.000'),
      );
      final parsed = CacheEnvelope.tryParse(env.toJson());
      expect(parsed, isNotNull);
      expect(parsed!.isApiOrigin, isTrue);
      expect((parsed.data as Map)['featured_products'], isNotEmpty);
    });

    test('stale readSource when older than fresh window', () {
      final env = CacheEnvelope(
        data: {},
        source: DataSourceKind.remote,
        cachedAt: DateTime.now().subtract(const Duration(hours: 2)),
      );
      expect(env.readSource, DataSourceKind.staleCache);
      expect(env.isFresh(), isFalse);
    });
  });
}
