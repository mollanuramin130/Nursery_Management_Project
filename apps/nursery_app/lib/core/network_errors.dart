import 'package:dio/dio.dart';
import 'package:nursery_app/core/api_client.dart';

/// QA-37 — unified network / HTTP failure classification (Customer Mobile).
enum NetworkKind {
  online,
  offline,
  connecting,
  reconnecting,
  apiUnavailable,
  apiTimeout,
  serverError,
  rateLimited,
  authExpired,
  unknownError,
}

class ClassifiedNetworkError {
  const ClassifiedNetworkError({
    required this.kind,
    required this.userMessage,
    this.statusCode,
    this.retryable = true,
  });

  final NetworkKind kind;
  final String userMessage;
  final int? statusCode;
  final bool retryable;
}

/// Map Dio / HTTP failures to shopper-safe copy. Never expose stack traces.
ClassifiedNetworkError classifyDioException(DioException e) {
  final status = e.response?.statusCode;
  final apiMsg = _envelopeMessage(e.response?.data);

  if (e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.receiveTimeout ||
      e.type == DioExceptionType.sendTimeout) {
    return ClassifiedNetworkError(
      kind: NetworkKind.apiTimeout,
      userMessage: 'Connection is taking too long. Please try again.',
      statusCode: status,
    );
  }

  if (e.type == DioExceptionType.connectionError ||
      (e.type == DioExceptionType.unknown && e.response == null)) {
    final msg = (e.message ?? '').toLowerCase();
    final simulated = msg.contains('simulated');
    // DEBUG network-sim only. A real phone DNS/socket failure is NOT "offline"
    // just because the emulator host 10.0.2.2 is unreachable.
    if (simulated) {
      return const ClassifiedNetworkError(
        kind: NetworkKind.offline,
        userMessage: "You're offline. Check your internet connection.",
      );
    }
    final refused = msg.contains('connection refused');
    return ClassifiedNetworkError(
      kind: NetworkKind.apiUnavailable,
      userMessage: refused
          ? 'Unable to connect to GreenLeaf right now.'
          : 'Connection temporarily unavailable.',
      statusCode: status,
    );
  }

  return classifyHttpStatus(status, apiMsg);
}

ClassifiedNetworkError classifyHttpStatus(int? status, [String? apiMessage]) {
  switch (status) {
    case 401:
      return ClassifiedNetworkError(
        kind: NetworkKind.authExpired,
        userMessage: 'Your session has expired. Please sign in again.',
        statusCode: status,
        retryable: false,
      );
    case 403:
      return ClassifiedNetworkError(
        kind: NetworkKind.unknownError,
        userMessage: "You don't have permission to do that.",
        statusCode: status,
        retryable: false,
      );
    case 404:
      return ClassifiedNetworkError(
        kind: NetworkKind.unknownError,
        userMessage: apiMessage?.trim().isNotEmpty == true
            ? apiMessage!.trim()
            : 'We could not find that item.',
        statusCode: status,
        retryable: false,
      );
    case 408:
      return const ClassifiedNetworkError(
        kind: NetworkKind.apiTimeout,
        userMessage: 'The request took too long. Please try again.',
        statusCode: 408,
      );
    case 409:
      return ClassifiedNetworkError(
        kind: NetworkKind.unknownError,
        userMessage: apiMessage?.trim().isNotEmpty == true
            ? apiMessage!.trim()
            : 'This action conflicts with the current state. Please refresh.',
        statusCode: status,
        retryable: false,
      );
    case 422:
      return ClassifiedNetworkError(
        kind: NetworkKind.unknownError,
        userMessage: apiMessage?.trim().isNotEmpty == true
            ? apiMessage!.trim()
            : 'Please check your details and try again.',
        statusCode: status,
        retryable: false,
      );
    case 429:
      return ClassifiedNetworkError(
        kind: NetworkKind.rateLimited,
        userMessage: apiMessage?.trim().isNotEmpty == true &&
                !RegExp(r'^too many attempts\.?$', caseSensitive: false)
                    .hasMatch(apiMessage!.trim())
            ? apiMessage.trim()
            : "You're doing that too quickly. Please wait a moment, then try again.",
        statusCode: status,
      );
    case 500:
      return const ClassifiedNetworkError(
        kind: NetworkKind.serverError,
        userMessage:
            'GreenLeaf server is temporarily unavailable. Please try again.',
        statusCode: 500,
      );
    case 502:
    case 503:
    case 504:
      return ClassifiedNetworkError(
        kind: NetworkKind.apiUnavailable,
        userMessage: 'Unable to connect to GreenLeaf right now.',
        statusCode: status,
      );
    default:
      if (apiMessage != null && apiMessage.trim().isNotEmpty) {
        return ClassifiedNetworkError(
          kind: NetworkKind.unknownError,
          userMessage: _sanitize(apiMessage),
          statusCode: status,
        );
      }
      return ClassifiedNetworkError(
        kind: NetworkKind.unknownError,
        userMessage: 'Something went wrong. Please try again.',
        statusCode: status,
      );
  }
}

ClassifiedNetworkError classifyApiException(ApiException e) {
  if (e.statusCode != null) {
    return classifyHttpStatus(e.statusCode, e.message);
  }
  final lower = e.message.toLowerCase();
  if (lower.contains('timed out') || lower.contains('taking too long')) {
    return ClassifiedNetworkError(
      kind: NetworkKind.apiTimeout,
      userMessage: 'Connection is taking too long. Please try again.',
      statusCode: e.statusCode,
    );
  }
  if (lower.contains('offline') || lower.contains('internet')) {
    return ClassifiedNetworkError(
      kind: NetworkKind.offline,
      userMessage: 'Connection temporarily unavailable.',
      statusCode: e.statusCode,
    );
  }
  if (lower.contains('unavailable') || lower.contains('unable to connect')) {
    return ClassifiedNetworkError(
      kind: NetworkKind.apiUnavailable,
      userMessage: 'Unable to connect to GreenLeaf right now.',
      statusCode: e.statusCode,
    );
  }
  return ClassifiedNetworkError(
    kind: NetworkKind.unknownError,
    userMessage: _sanitize(e.message),
    statusCode: e.statusCode,
  );
}

/// True when refresh/login transport failed but the refresh token may still be valid.
bool isTransientTransportFailure(Object error) {
  if (error is DioException) {
    return error.response == null &&
        (error.type == DioExceptionType.connectionTimeout ||
            error.type == DioExceptionType.receiveTimeout ||
            error.type == DioExceptionType.sendTimeout ||
            error.type == DioExceptionType.connectionError ||
            error.type == DioExceptionType.unknown);
  }
  return false;
}

String? _envelopeMessage(dynamic data) {
  if (data is Map && data['message'] != null) {
    return data['message'].toString();
  }
  return null;
}

String _sanitize(String raw) {
  final lower = raw.toLowerCase();
  if (lower.contains('connection refused') ||
      lower.contains('api_proxy') ||
      lower.contains('artisan')) {
    return 'Unable to connect to GreenLeaf right now.';
  }
  if (lower.contains('socketexception') ||
      lower.contains('failed host lookup') ||
      lower.contains('xmlhttprequest') ||
      lower.contains('dioexception')) {
    return 'Connection temporarily unavailable.';
  }
  if (raw.length > 180) return 'Something went wrong. Please try again.';
  return raw.trim();
}

String bannerCopy(NetworkKind kind) {
  switch (kind) {
    case NetworkKind.offline:
      return "You're offline";
    case NetworkKind.reconnecting:
    case NetworkKind.connecting:
      return 'Checking connection…';
    case NetworkKind.apiUnavailable:
      return 'Unable to connect to GreenLeaf right now.';
    case NetworkKind.serverError:
      return 'GreenLeaf server is temporarily unavailable.';
    case NetworkKind.apiTimeout:
      return 'Connection is taking too long.';
    case NetworkKind.rateLimited:
      return 'Too many requests · Please wait a moment';
    case NetworkKind.authExpired:
      return 'Your session has expired';
    case NetworkKind.online:
    case NetworkKind.unknownError:
      return 'Unable to refresh.';
  }
}

/// Compact banner line. Never says "You're offline" for HTTP 5xx / timeouts.
String statusBannerText({
  required NetworkKind kind,
  required bool servingLocal,
  String? message,
}) {
  String base(String live, String withSaved) =>
      servingLocal ? withSaved : live;

  switch (kind) {
    case NetworkKind.offline:
      return base(
        "You're offline",
        "You're offline · Showing saved data",
      );
    case NetworkKind.serverError:
      return base(
        'GreenLeaf server is temporarily unavailable.',
        'GreenLeaf server is temporarily unavailable · Showing saved data',
      );
    case NetworkKind.apiUnavailable:
      return base(
        'Unable to connect to GreenLeaf right now.',
        'Unable to connect to GreenLeaf right now · Showing saved data',
      );
    case NetworkKind.apiTimeout:
      return base(
        'Connection is taking too long.',
        'Connection is taking too long · Showing saved data',
      );
    case NetworkKind.connecting:
    case NetworkKind.reconnecting:
      return 'Checking connection…';
    default:
      if (servingLocal) return 'Unable to refresh · Showing saved data';
      return message ?? bannerCopy(kind);
  }
}

/// Banner is for a verified degraded network state only.
/// Cache leftover (`servingLocal`) must not keep "You're offline" after the
/// API is healthy again (kind == online).
bool shouldShowNetworkBanner({
  required NetworkKind kind,
  required bool showBanner,
  required bool servingLocal,
}) {
  if (kind == NetworkKind.online ||
      kind == NetworkKind.connecting ||
      kind == NetworkKind.reconnecting) {
    return false;
  }
  return showBanner || servingLocal;
}
