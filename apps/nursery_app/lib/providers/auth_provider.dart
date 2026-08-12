import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_messages.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/models/models.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider(this._api, this._storage);

  final ApiClient _api;
  final SessionStorage _storage;

  User? user;
  bool bootstrapped = false;
  bool loading = false;
  String? error;

  bool get isAuthenticated => user != null;

  Future<void> bootstrap() async {
    final access = await _storage.getAccessToken();
    final refresh = await _storage.getRefreshToken();
    if (access == null && refresh == null) {
      bootstrapped = true;
      notifyListeners();
      return;
    }
    try {
      user = await _api.getData(
        '/auth/me',
        map: (data) => User.fromJson(Map<String, dynamic>.from(data as Map)),
      );
      await _registerDeviceQuietly();
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
      // X-Cart-Token is attached by ApiClient so the backend can merge the
      // guest cart during this request — do not clear the token beforehand.
      final data = await _api.sendData<Map<String, dynamic>>(
        'POST',
        '/auth/login',
        body: {
          'email': email,
          'password': password,
          'device': {'platform': AppConfig.platform},
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      await _applyAuthSession(data);
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = AuthMessages.fromException(e);
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    if (loading) return false;
    loading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.sendData<Map<String, dynamic>>(
        'POST',
        '/auth/register',
        body: {
          'name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
          if (phone != null && phone.isNotEmpty) 'phone': phone,
          'device': {'platform': AppConfig.platform},
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      // Register returns tokens + user and merges guest cart server-side.
      await _applyAuthSession(data);
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = AuthMessages.fromException(e);
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> forgotPassword(String email) async {
    if (loading) return false;
    loading = true;
    error = null;
    notifyListeners();
    try {
      await _api.sendData(
        'POST',
        '/auth/forgot-password',
        body: {'email': email},
        map: (_) => null,
      );
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = AuthMessages.fromException(e);
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> updateProfile({String? name, String? phone}) async {
    if (loading) return false;
    loading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.sendData<Map<String, dynamic>>(
        'PUT',
        '/customer/profile',
        body: {
          'name': ?name,
          // Empty phone clears optional field when provided.
          if (phone != null) 'phone': phone.isEmpty ? null : phone,
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      user = User.fromJson(data);
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = AuthMessages.fromException(e);
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    final refresh = await _storage.getRefreshToken();
    final deviceId = await _storage.getDeviceId();
    try {
      if (deviceId != null) {
        await _api.sendData(
          'POST',
          '/devices/deactivate',
          body: {'device_id': deviceId},
          map: (_) => null,
        );
      }
    } catch (_) {}
    try {
      if (refresh != null) {
        await _api.sendData(
          'POST',
          '/auth/logout',
          body: {'refresh_token': refresh},
          map: (_) => null,
        );
      }
    } catch (_) {}
    // Clear auth only — do not invent cart deletion. Guest cart resumes via API.
    await _storage.clearTokens();
    user = null;
    notifyListeners();
  }

  void clearError() {
    if (error == null) return;
    error = null;
    notifyListeners();
  }

  /// Persist session after login/register. Guest cart was already merged by
  /// the backend using the request's X-Cart-Token; drop the guest token so
  /// subsequent cart calls use the authenticated user cart.
  Future<void> _applyAuthSession(Map<String, dynamic> data) async {
    await _storage.saveTokens(
      accessToken: data['access_token'] as String,
      refreshToken: data['refresh_token'] as String,
    );
    await _storage.saveCartToken(null);
    user = User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    await _registerDeviceQuietly();
  }

  Future<void> _registerDeviceQuietly() async {
    try {
      final deviceId = await _storage.ensureDeviceId();
      await _api.sendData(
        'POST',
        '/devices/register',
        body: {
          'platform': AppConfig.platform,
          'device_id': deviceId,
          'app_version': AppConfig.appVersion,
        },
        map: (_) => null,
      );
    } catch (_) {}
  }
}
