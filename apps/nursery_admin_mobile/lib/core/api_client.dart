import 'package:dio/dio.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errorCode});

  final String message;
  final int? statusCode;
  final String? errorCode;

  @override
  String toString() => message;

  String get userMessage {
    switch (statusCode) {
      case 401:
        return 'Session expired. Please sign in again.';
      case 403:
        return "You don't have permission to perform this action.";
      case 404:
        return 'Not found.';
      case 409:
        return message.isNotEmpty
            ? message
            : 'The data changed. Refresh and try again.';
      case 422:
        return message.isNotEmpty
            ? message
            : 'Please check the entered information.';
      case 429:
        return 'Too many requests. Wait a moment and retry.';
      default:
        if (statusCode == null) {
          return 'Unable to connect. Check your internet connection.';
        }
        return message.isNotEmpty ? message : 'Something went wrong.';
    }
  }
}

class ApiClient {
  ApiClient(this._storage) {
    _dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 20),
        receiveTimeout: const Duration(seconds: 30),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Platform': AppConfig.platform,
          'X-App-Version': AppConfig.appVersion,
          'X-Client': 'admin-mobile',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final access = await _storage.getAccessToken();
          if (access != null && access.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $access';
          }
          final device = await _storage.ensureDeviceId();
          options.headers['X-Device-Id'] = device;
          options.headers.putIfAbsent(
            'X-Request-Id',
            () =>
                'ops_${DateTime.now().millisecondsSinceEpoch}_${options.hashCode.toRadixString(16)}',
          );
          handler.next(options);
        },
        onError: (error, handler) async {
          final response = error.response;
          final request = error.requestOptions;
          final alreadyRetried = request.extra['retried'] == true;
          final isAuthCall = request.path.contains('/auth/login') ||
              request.path.contains('/auth/refresh') ||
              request.path.contains('/auth/logout');

          if (response?.statusCode == 401 && !alreadyRetried && !isAuthCall) {
            final refreshed = await _refreshTokens();
            if (refreshed) {
              final opts = request.copyWith(
                extra: {...request.extra, 'retried': true},
              );
              final access = await _storage.getAccessToken();
              if (access != null) {
                opts.headers['Authorization'] = 'Bearer $access';
              }
              try {
                final clone = await _dio.fetch(opts);
                return handler.resolve(clone);
              } catch (_) {
                return handler.next(error);
              }
            }
          }
          handler.next(error);
        },
      ),
    );
  }

  final SessionStorage _storage;
  late final Dio _dio;

  Future<bool> _refreshTokens() async {
    final refresh = await _storage.getRefreshToken();
    if (refresh == null || refresh.isEmpty) return false;
    try {
      final res = await Dio(
        BaseOptions(
          baseUrl: AppConfig.apiBaseUrl,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        ),
      ).post('/auth/refresh', data: {'refresh_token': refresh});
      final body = res.data;
      if (body is! Map || body['success'] != true) return false;
      final data = body['data'];
      if (data is! Map) return false;
      final access = data['access_token']?.toString();
      final nextRefresh = data['refresh_token']?.toString();
      if (access == null || nextRefresh == null) return false;
      await _storage.saveTokens(
        accessToken: access,
        refreshToken: nextRefresh,
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  Never _throwDio(DioException e) {
    final data = e.response?.data;
    String message = 'Request failed';
    String? code;
    if (data is Map) {
      message = (data['message'] ?? message).toString();
      final meta = data['meta'];
      if (meta is Map && meta['error_code'] != null) {
        code = meta['error_code'].toString();
      }
    } else if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      message = 'Unable to connect. Check your internet connection.';
    }
    throw ApiException(
      message,
      statusCode: e.response?.statusCode,
      errorCode: code,
    );
  }

  dynamic _unwrap(Response response) {
    final body = response.data;
    if (body is! Map) {
      throw ApiException('Invalid response', statusCode: response.statusCode);
    }
    if (body['success'] != true) {
      throw ApiException(
        (body['message'] ?? 'Request failed').toString(),
        statusCode: response.statusCode,
      );
    }
    return body;
  }

  Future<T> getData<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.get(path, queryParameters: query);
      final body = _unwrap(res);
      return map(body['data']);
    } on DioException catch (e) {
      _throwDio(e);
    }
  }

  Future<({T data, Map<String, dynamic>? meta})> getDataWithMeta<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.get(path, queryParameters: query);
      final body = _unwrap(res) as Map;
      final meta = body['meta'] is Map
          ? Map<String, dynamic>.from(body['meta'] as Map)
          : null;
      return (data: map(body['data']), meta: meta);
    } on DioException catch (e) {
      _throwDio(e);
    }
  }

  Future<T> sendData<T>(
    String method,
    String path, {
    Object? body,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.request(
        path,
        data: body,
        options: Options(method: method),
      );
      final unwrapped = _unwrap(res);
      return map(unwrapped['data']);
    } on DioException catch (e) {
      _throwDio(e);
    }
  }
}
