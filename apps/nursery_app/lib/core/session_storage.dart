import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStorage {
  SessionStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _access = 'gl_access_token';
  static const _refresh = 'gl_refresh_token';
  static const _cart = 'gl_cart_token';
  static const _device = 'gl_device_id';

  Future<String?> getAccessToken() => _storage.read(key: _access);
  Future<String?> getRefreshToken() => _storage.read(key: _refresh);
  Future<String?> getCartToken() => _storage.read(key: _cart);
  Future<String?> getDeviceId() => _storage.read(key: _device);

  Future<String> ensureDeviceId() async {
    final existing = await getDeviceId();
    if (existing != null && existing.isNotEmpty) return existing;
    final id = 'android-${DateTime.now().millisecondsSinceEpoch}';
    await _storage.write(key: _device, value: id);
    return id;
  }

  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: _access, value: accessToken);
    await _storage.write(key: _refresh, value: refreshToken);
  }

  Future<void> clearTokens() async {
    await _storage.delete(key: _access);
    await _storage.delete(key: _refresh);
  }

  Future<void> saveCartToken(String? token) async {
    if (token == null || token.isEmpty) {
      await _storage.delete(key: _cart);
      return;
    }
    await _storage.write(key: _cart, value: token);
  }
}
