import 'package:dio/dio.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/session_storage.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errorCode});

  final String message;
  final int? statusCode;
  final String? errorCode;

  @override
  String toString() => message;
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
          final cart = await _storage.getCartToken();
          if (cart != null && cart.isNotEmpty) {
            options.headers['X-Cart-Token'] = cart;
          }
          final guest = await _storage.ensureDeviceId();
          options.headers['X-Guest-Token'] = guest;
          options.headers.putIfAbsent(
            'X-Request-Id',
            () =>
                'android_${DateTime.now().millisecondsSinceEpoch}_${options.hashCode.toRadixString(16)}',
          );
          handler.next(options);
        },
        onResponse: (response, handler) async {
          await _persistCartToken(response);
          handler.next(response);
        },
        onError: (error, handler) async {
          final response = error.response;
          final request = error.requestOptions;
          final alreadyRetried = request.extra['retried'] == true;
          final isAuthCall =
              request.path.contains('/auth/login') ||
              request.path.contains('/auth/register') ||
              request.path.contains('/auth/refresh') ||
              request.path.contains('/auth/forgot-password') ||
              request.path.contains('/auth/reset-password');

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
              } catch (e) {
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

  Dio get raw => _dio;

  Future<void> _persistCartToken(Response response) async {
    final header = response.headers.value('x-cart-token');
    if (header != null && header.isNotEmpty) {
      await _storage.saveCartToken(header);
    }
    final data = response.data;
    if (data is Map && data['data'] is Map) {
      final token = data['data']['cart_token'];
      if (token is String && token.isNotEmpty) {
        await _storage.saveCartToken(token);
      }
    }
  }

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

      final body = res.data as Map<String, dynamic>;
      if (body['success'] != true) {
        await _storage.clearTokens();
        return false;
      }
      final data = body['data'] as Map<String, dynamic>;
      await _storage.saveTokens(
        accessToken: data['access_token'] as String,
        refreshToken: data['refresh_token'] as String,
      );
      return true;
    } catch (_) {
      await _storage.clearTokens();
      return false;
    }
  }

  Future<T> getData<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.get(path, queryParameters: query);
      return _unwrap(res.data, map);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  /// Like [getData] but also returns response `meta` (pagination, etc.).
  Future<({T data, Map<String, dynamic>? meta})> getDataWithMeta<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.get(path, queryParameters: query);
      final body = res.data as Map<String, dynamic>;
      if (body['success'] != true) {
        throw ApiException(
          body['message']?.toString() ?? 'Request failed',
          errorCode: (body['meta'] as Map?)?['error_code']?.toString(),
        );
      }
      final meta = body['meta'] is Map
          ? Map<String, dynamic>.from(body['meta'] as Map)
          : null;
      return (data: map(body['data']), meta: meta);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Future<T> sendData<T>(
    String method,
    String path, {
    Object? body,
    Map<String, dynamic>? headers,
    required T Function(dynamic data) map,
  }) async {
    try {
      final res = await _dio.request(
        path,
        data: body,
        options: Options(method: method, headers: headers),
      );
      return _unwrap(res.data, map);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  T _unwrap<T>(dynamic raw, T Function(dynamic data) map) {
    final body = raw as Map<String, dynamic>;
    if (body['success'] != true) {
      throw ApiException(
        body['message']?.toString() ?? 'Request failed',
        errorCode: (body['meta'] as Map?)?['error_code']?.toString(),
      );
    }
    return map(body['data']);
  }

  ApiException _toApiException(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] != null) {
      return ApiException(
        data['message'].toString(),
        statusCode: e.response?.statusCode,
        errorCode: (data['meta'] as Map?)?['error_code']?.toString(),
      );
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return ApiException(
        'The request timed out. Please try again.',
        statusCode: e.response?.statusCode,
      );
    }
    if (e.type == DioExceptionType.connectionError) {
      return ApiException(
        'Unable to connect. Please check your internet connection.',
        statusCode: e.response?.statusCode,
      );
    }
    return ApiException(
      'Unable to connect. Please check your internet connection.',
      statusCode: e.response?.statusCode,
    );
  }
}
