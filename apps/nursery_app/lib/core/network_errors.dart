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
    final looksOffline = msg.contains('socket') ||
        msg.contains('failed host lookup') ||
        msg.contains('network is unreachable') ||
        msg.contains('connection refused') ||
        msg.contains('no address associated');
    return ClassifiedNetworkError(
      kind: looksOffline ? NetworkKind.offline : NetworkKind.apiUnavailable,
      userMessage: looksOffline
          ? "You're offline. Check your internet connection."
          : 'GreenLeaf is temporarily unavailable. Please try again.',
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
        userMessage: 'Something went wrong on our side. Please try again.',
        statusCode: 500,
      );
    case 502:
    case 503:
    case 504:
      return ClassifiedNetworkError(
        kind: NetworkKind.apiUnavailable,
        userMessage: 'GreenLeaf is temporarily unavailable. Please try again.',
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
      userMessage: "You're offline. Check your internet connection.",
      statusCode: e.statusCode,
    );
  }
  if (lower.contains('unavailable') || lower.contains('unable to connect')) {
    return ClassifiedNetworkError(
      kind: NetworkKind.apiUnavailable,
      userMessage: 'GreenLeaf is temporarily unavailable. Please try again.',
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
  if (lower.contains('socketexception') ||
      lower.contains('failed host lookup') ||
      lower.contains('connection refused') ||
      lower.contains('xmlhttprequest') ||
      lower.contains('dioexception')) {
    return "You're offline. Check your internet connection.";
  }
  if (raw.length > 180) return 'Something went wrong. Please try again.';
  return raw.trim();
}

String bannerCopy(NetworkKind kind) {
  switch (kind) {
    case NetworkKind.offline:
      return "You're offline · Reconnecting when possible";
    case NetworkKind.reconnecting:
    case NetworkKind.connecting:
      return 'Connection interrupted · Reconnecting…';
    case NetworkKind.apiUnavailable:
    case NetworkKind.serverError:
      return 'GreenLeaf is temporarily unavailable · Retrying…';
    case NetworkKind.apiTimeout:
      return 'Connection is slow · Still trying…';
    case NetworkKind.rateLimited:
      return 'Too many requests · Please wait a moment';
    case NetworkKind.authExpired:
      return 'Your session has expired';
    case NetworkKind.online:
    case NetworkKind.unknownError:
      return 'Connection interrupted · Reconnecting…';
  }
}
