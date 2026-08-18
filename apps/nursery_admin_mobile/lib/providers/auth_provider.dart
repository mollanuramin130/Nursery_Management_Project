import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/app_error.dart';
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
    final cached = await _storage.readUserJson();
    if (cached != null) {
      try {
        final restored = AdminUser.fromJson(cached);
        if (isStaffUser(
          roles: restored.roles,
          permissions: restored.permissions,
        )) {
          user = restored;
        }
      } catch (_) {
        user = null;
      }
    }
    bootstrapped = true;
    notifyListeners();
    unawaited(_revalidateSession());
  }

  Future<void> _revalidateSession() async {
    final access = await _storage.getAccessToken();
    final refresh = await _storage.getRefreshToken();
    if (access == null && refresh == null) {
      if (user != null) {
        user = null;
        notifyListeners();
      }
      return;
    }
    try {
      final me = await _api.getData(
        '/auth/me',
        map: (data) =>
            AdminUser.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      if (!isStaffUser(roles: me.roles, permissions: me.permissions)) {
        await _storage.clearTokens();
        user = null;
        error = 'Staff account required for Operations app.';
        notifyListeners();
        return;
      }
      user = me;
      await _storage.saveUserJson(me.toJson());
      await _registerDeviceQuietly();
      notifyListeners();
    } catch (e) {
      if (e is ApiException && (e.statusCode == 401 || e.statusCode == 403)) {
        await _storage.clearTokens();
        user = null;
        notifyListeners();
      }
    }
  }

  Future<bool> login(String email, String password) async {
    if (loading) {
      if (kDebugMode) {
        debugPrint('[AUTH] duplicate login prevented');
      }
      return false;
    }
    loading = true;
    error = null;
    notifyListeners();
    try {
      if (kDebugMode) {
        debugPrint('[AUTH] login request started');
      }
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
      await _storage.saveUserJson(me.toJson());
      await _registerDeviceQuietly();
      loading = false;
      if (kDebugMode) {
        debugPrint('[AUTH] login request completed');
      }
      notifyListeners();
      return true;
    } catch (e) {
      error = sanitizeCaughtError(e);
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
    await clearLocalSession(sessionExpired: false);
    error = null;
    notifyListeners();
  }

  /// Clears in-memory auth after refresh failure (tokens already wiped).
  /// Shows a session-expired message on the login screen.
  Future<void> clearLocalSession({bool sessionExpired = true}) async {
    await _storage.clearTokens();
    user = null;
    if (sessionExpired) {
      error = 'Session expired. Please sign in again.';
    }
    notifyListeners();
  }

  Future<void> _registerDeviceQuietly() async {
    try {
      final deviceId = await _storage.ensureDeviceId();
      const pushToken = String.fromEnvironment('FCM_PUSH_TOKEN', defaultValue: '');
      await _api.sendData(
        'POST',
        '/devices/register',
        body: {
          'device_id': deviceId,
          'platform': AppConfig.platform,
          'app_version': AppConfig.appVersion,
          if (pushToken.isNotEmpty) 'push_token': pushToken,
        },
        map: (_) => null,
      );
    } catch (_) {}
  }
}
