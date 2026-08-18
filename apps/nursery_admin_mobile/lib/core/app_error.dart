import 'package:nursery_admin_mobile/core/api_client.dart';

enum ErrorCategory {
  temporaryNetwork,
  apiUnavailable,
  authentication,
  validation,
  payment,
  unexpected,
  rateLimited,
}

ErrorCategory categoryForStatus(int? statusCode) {
  if (statusCode == null) return ErrorCategory.temporaryNetwork;
  if (statusCode == 401) return ErrorCategory.authentication;
  if (statusCode == 408 || statusCode == 504) {
    return statusCode == 408
        ? ErrorCategory.temporaryNetwork
        : ErrorCategory.apiUnavailable;
  }
  if (statusCode == 429) return ErrorCategory.rateLimited;
  if (statusCode == 500 || statusCode == 502 || statusCode == 503) {
    return ErrorCategory.apiUnavailable;
  }
  if (statusCode == 403 ||
      statusCode == 404 ||
      statusCode == 409 ||
      statusCode == 422) {
    return ErrorCategory.validation;
  }
  if (statusCode >= 500) return ErrorCategory.apiUnavailable;
  return ErrorCategory.unexpected;
}

bool shouldOfferSupport(
  ErrorCategory category, {
  int retryCount = 0,
  bool userRequested = false,
}) {
  if (userRequested) return true;
  switch (category) {
    case ErrorCategory.unexpected:
    case ErrorCategory.payment:
      return true;
    case ErrorCategory.apiUnavailable:
      return retryCount >= 2;
    case ErrorCategory.temporaryNetwork:
    case ErrorCategory.authentication:
    case ErrorCategory.validation:
    case ErrorCategory.rateLimited:
      return false;
  }
}

String sanitizeCaughtError(Object error) {
  if (error is ApiException) return error.userMessage;
  return sanitizeOpsMessage(error.toString());
}

String sanitizeOpsMessage(String? raw) {
  if (raw == null || raw.trim().isEmpty) {
    return 'Something went wrong while loading this section.';
  }
  final lower = raw.toLowerCase();
  if (lower.contains('exception') ||
      lower.contains('socket') ||
      lower.contains('dio') ||
      lower.contains('stack') ||
      lower.contains('sql') ||
      lower.contains('password') ||
      lower.contains('jwt') ||
      lower.contains('cookie') ||
      lower.contains('rzp_') ||
      lower.contains('artisan') ||
      lower.contains('api_proxy') ||
      lower.contains('null check') ||
      lower.contains('bad state') ||
      lower.contains('nosuchmethod') ||
      raw.length > 140) {
    return 'Something went wrong while loading this section.';
  }
  return raw.trim();
}

String redactSecrets(String raw) {
  var out = raw;
  out = out.replaceAll(
    RegExp(r'eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'),
    '[redacted]',
  );
  out = out.replaceAll(
    RegExp(
      r'(password|passwd|secret|token|jwt|cookie|authorization)[=:]\s*\S+',
      caseSensitive: false,
    ),
    r'$1=[redacted]',
  );
  out = out.replaceAll(RegExp(r'rzp_(live|test)_[A-Za-z0-9]+'), '[redacted]');
  return out;
}
