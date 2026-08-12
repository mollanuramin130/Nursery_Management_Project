import 'package:flutter/foundation.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/core/permissions.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';
import 'package:nursery_admin_mobile/models/models.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider(this._api, this._storage);

  final ApiClient _api;
  final SessionStorage _storage;

  AdminUser? user;
  bool bootstrapped = false;
  bool loading = false;
  String? error;

  bool get isAuthenticated => user != null;

  bool can(String permission) =>
      user != null &&
      hasPermission(user!.permissions, user!.roles, permission);

  bool canAny(List<String> needed) =>
      user != null &&
      hasAnyPermission(user!.permissions, user!.roles, needed);

  Future<void> bootstrap() async {
    final access = await _storage.getAccessToken();
    final refresh = await _storage.getRefreshToken();
    if (access == null && refresh == null) {
      bootstrapped = true;
      notifyListeners();
      return;
    }
    try {
      final me = await _api.getData(
        '/auth/me',
        map: (data) => AdminUser.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      if (!isStaffUser(roles: me.roles, permissions: me.permissions)) {
        await _storage.clearTokens();
        user = null;
        error = 'Staff account required for Operations app.';
      } else {
        user = me;
        await _registerDeviceQuietly();
      }
    } catch (_) {
      await _storage.clearTokens();
      user = null;
    }
    bootstrapped = true;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    if (loading) return false;
    loading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.sendData<Map<String, dynamic>>(
        'POST',
        '/auth/login',
        body: {
          'email': email.trim(),
          'password': password,
          'device': {
            'platform': AppConfig.platform,
            'app': 'admin_mobile',
          },
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );

      final access = data['access_token']?.toString();
      final refresh = data['refresh_token']?.toString();
      if (access == null || refresh == null) {
        throw ApiException('Invalid login response');
      }
      await _storage.saveTokens(accessToken: access, refreshToken: refresh);

      // Prefer /auth/me for full permissions list.
      final me = await _api.getData(
        '/auth/me',
        map: (d) => AdminUser.fromJson(Map<String, dynamic>.from(d as Map)),
      );
      if (!isStaffUser(roles: me.roles, permissions: me.permissions)) {
        await _storage.clearTokens();
        user = null;
        error = 'This account is not authorized for GreenLeaf Operations.';
        loading = false;
        notifyListeners();
        return false;
      }
      user = me;
      await _registerDeviceQuietly();
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = e is ApiException ? e.userMessage : e.toString();
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    final refresh = await _storage.getRefreshToken();
    try {
      if (refresh != null) {
        await _api.sendData(
          'POST',
          '/auth/logout',
          body: {'refresh_token': refresh},
          map: (_) => null,
        );
      }
    } catch (_) {
      // Always clear local session.
    }
    try {
      await _api.sendData(
        'POST',
        '/devices/deactivate',
        body: {},
        map: (_) => null,
      );
    } catch (_) {}
    await _storage.clearTokens();
    user = null;
    notifyListeners();
  }

  Future<void> _registerDeviceQuietly() async {
    try {
      final deviceId = await _storage.ensureDeviceId();
      await _api.sendData(
        'POST',
        '/devices/register',
        body: {
          'device_id': deviceId,
          'platform': AppConfig.platform,
          'app': 'admin_mobile',
        },
        map: (_) => null,
      );
    } catch (_) {}
  }
}
