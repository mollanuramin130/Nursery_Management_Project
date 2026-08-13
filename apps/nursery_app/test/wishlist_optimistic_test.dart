import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';

/// QA-36-008 / QA-37 — optimistic wishlist remove + session clear hook.
class _FakeApi extends ApiClient {
  _FakeApi() : super(SessionStorage(storage: const FlutterSecureStorage()));

  bool failDelete = false;
  int deleteCalls = 0;

  @override
  Future<T> sendData<T>(
    String method,
    String path, {
    Object? body,
    Map<String, dynamic>? headers,
    required T Function(dynamic data) map,
  }) async {
    if (method == 'POST' && path == '/wishlist') {
      return map(null);
    }
    if (method == 'DELETE' && path.startsWith('/wishlist/')) {
      deleteCalls++;
      await Future<void>.delayed(const Duration(milliseconds: 8));
      if (failDelete) {
        throw ApiException('delete failed', statusCode: 500);
      }
      return map(null);
    }
    throw UnimplementedError('$method $path');
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  test('remove drops id before DELETE completes (optimistic)', () async {
    final api = _FakeApi();
    final wishlist = WishlistProvider(api);
    await wishlist.toggle(42);
    expect(wishlist.contains(42), isTrue);

    final pending = wishlist.remove(42);
    expect(wishlist.contains(42), isFalse);
    await pending;
    expect(wishlist.contains(42), isFalse);
    expect(api.deleteCalls, 1);
  });

  test('remove restores id when DELETE fails', () async {
    final api = _FakeApi()..failDelete = true;
    final wishlist = WishlistProvider(api);
    await wishlist.toggle(7);
    expect(wishlist.contains(7), isTrue);

    await expectLater(wishlist.remove(7), throwsA(isA<ApiException>()));
    expect(wishlist.contains(7), isTrue);
  });

  test('clearLocal empties hearts (session-expiry parity QA-37-001)', () async {
    final api = _FakeApi();
    final wishlist = WishlistProvider(api);
    await wishlist.toggle(9);
    expect(wishlist.contains(9), isTrue);
    wishlist.clearLocal();
    expect(wishlist.contains(9), isFalse);
  });
}
