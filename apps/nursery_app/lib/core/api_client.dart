import 'package:dio/dio.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/core/token_refresh_coordinator.dart';
import 'package:nursery_app/data/mock_data_mode.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errorCode});

  final String message;
  final int? statusCode;
  final String? errorCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this._storage, {this.onSessionExpired}) {
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
          // QA-37 DEBUG — network simulation / offline simulation (no Wi‑Fi toggle).
          final sim = networkSimulation?.call() ?? NetworkSimulation.off;
          final mode = mockDataMode?.call() ?? MockDataMode.auto;
          // reconnect is a UI control — treated as off for transport.
          if (mode == MockDataMode.offlineSimulation ||
              sim == NetworkSimulation.offline) {
            return handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.connectionError,
                error: 'Simulated offline',
                message: 'Failed host lookup (simulated)',
              ),
            );
          }
          if (sim == NetworkSimulation.timeout) {
            return handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.receiveTimeout,
                message: 'Simulated timeout',
              ),
            );
          }
          if (sim == NetworkSimulation.apiError) {
            return handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.badResponse,
                response: Response(
                  requestOptions: options,
                  statusCode: 503,
                  data: {
                    'success': false,
                    'message': 'GreenLeaf is temporarily unavailable.',
                  },
                ),
              ),
            );
          }
          if (sim == NetworkSimulation.slow) {
            await Future<void>.delayed(const Duration(seconds: 3));
          }

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
            final refreshed = await _refreshCoordinator.run(_refreshTokens);
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
  final TokenRefreshCoordinator _refreshCoordinator = TokenRefreshCoordinator();

  /// Called after refresh fails and tokens are cleared (QA-28).
  /// Clears in-memory auth so UI does not keep a stale signed-in user.
  void Function()? onSessionExpired;

  /// QA-37 — optional hooks for global connectivity banner.
  void Function(ClassifiedNetworkError error)? onTransportFailure;
  void Function()? onTransportSuccess;

  /// QA-37 DEBUG — wired from OfflineController in main.dart.
  NetworkSimulation Function()? networkSimulation;
  MockDataMode Function()? mockDataMode;

  /// Exposed for unit tests (QA-12).
  TokenRefreshCoordinator get refreshCoordinator => _refreshCoordinator;

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
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        ),
      ).post('/auth/refresh', data: {'refresh_token': refresh});

      final body = res.data as Map<String, dynamic>;
      if (body['success'] != true) {
        await _storage.clearTokens();
        onSessionExpired?.call();
        return false;
      }
      final data = body['data'] as Map<String, dynamic>;
      await _storage.saveTokens(
        accessToken: data['access_token'] as String,
        refreshToken: data['refresh_token'] as String,
      );
      return true;
    } on DioException catch (e) {
      // QA-37: transport failures must NOT log the user out.
      if (e.response == null) {
        return false;
      }
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        await _storage.clearTokens();
        onSessionExpired?.call();
      }
      return false;
    } catch (_) {
      // Unknown non-Dio failure — keep tokens; treat as transient.
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
      onTransportSuccess?.call();
      return _unwrap(res.data, map);
    } on DioException catch (e) {
      final classified = classifyDioException(e);
      onTransportFailure?.call(classified);
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
      onTransportSuccess?.call();
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
      onTransportFailure?.call(classifyDioException(e));
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
      onTransportSuccess?.call();
      return _unwrap(res.data, map);
    } on DioException catch (e) {
      onTransportFailure?.call(classifyDioException(e));
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

  String? _metaErrorCode(dynamic data) {
    if (data is! Map) return null;
    final meta = data['meta'];
    if (meta is Map) return meta['error_code']?.toString();
    return null;
  }

  ApiException _toApiException(DioException e) {
    // Keep classification aligned with network_errors.dart.
    final data = e.response?.data;
    final status = e.response?.statusCode;
    final apiMsg =
        data is Map && data['message'] != null ? data['message'].toString() : null;
    final code = _metaErrorCode(data);

    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return ApiException(
        'Connection is taking too long. Please try again.',
        statusCode: status,
      );
    }
    if (e.type == DioExceptionType.connectionError ||
        (e.type == DioExceptionType.unknown && e.response == null)) {
      return ApiException(
        "You're offline. Check your internet connection.",
        statusCode: status,
      );
    }
    if (status == 429) {
      return ApiException(
        apiMsg != null &&
                !RegExp(r'^too many attempts\.?$', caseSensitive: false)
                    .hasMatch(apiMsg.trim())
            ? apiMsg
            : "You're doing that too quickly. Please wait a moment, then try again.",
        statusCode: status,
        errorCode: code,
      );
    }
    if (status == 502 || status == 503 || status == 504) {
      return ApiException(
        'GreenLeaf is temporarily unavailable. Please try again.',
        statusCode: status,
      );
    }
    if (status == 500) {
      return ApiException(
        'Something went wrong on our side. Please try again.',
        statusCode: status,
      );
    }
    if (apiMsg != null && apiMsg.isNotEmpty) {
      return ApiException(apiMsg, statusCode: status, errorCode: code);
    }
    return ApiException(
      'GreenLeaf is temporarily unavailable. Please try again.',
      statusCode: status,
    );
  }
}
