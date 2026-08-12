import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Secure token storage for Admin Mobile (never SharedPreferences for tokens).
class SessionStorage {
  SessionStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _access = 'gl_ops_access_token';
  static const _refresh = 'gl_ops_refresh_token';
  static const _device = 'gl_ops_device_id';

  Future<String?> getAccessToken() => _storage.read(key: _access);
  Future<String?> getRefreshToken() => _storage.read(key: _refresh);
  Future<String?> getDeviceId() => _storage.read(key: _device);

  Future<String> ensureDeviceId() async {
    final existing = await getDeviceId();
    if (existing != null && existing.isNotEmpty) return existing;
    final id = 'ops-android-${DateTime.now().millisecondsSinceEpoch}';
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
}
