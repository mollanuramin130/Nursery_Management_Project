import 'package:nursery_app/core/network_errors.dart';

/// QA-43 — user-facing error categories. Maps onto existing [NetworkKind].
enum ErrorCategory {
  /// A — Wi-Fi/data blip, timeout, DNS. No helpline.
  temporaryNetwork,

  /// B — 502/503/504 / server connection. Helpline only if persistent.
  apiUnavailable,

  /// C — session/token. Never auto-helpline.
  authentication,

  /// D — validation / 404 / permission. Never helpline.
  validation,

  /// E — payment. Support OK without implying success.
  payment,

  /// F — unexpected runtime / widget crash.
  unexpected,

  rateLimited,
}

ErrorCategory categoryForNetwork(NetworkKind kind, {int? statusCode}) {
  switch (kind) {
    case NetworkKind.offline:
    case NetworkKind.connecting:
    case NetworkKind.reconnecting:
    case NetworkKind.apiTimeout:
      return ErrorCategory.temporaryNetwork;
    case NetworkKind.apiUnavailable:
    case NetworkKind.serverError:
      return ErrorCategory.apiUnavailable;
    case NetworkKind.authExpired:
      return ErrorCategory.authentication;
    case NetworkKind.rateLimited:
      return ErrorCategory.rateLimited;
    case NetworkKind.unknownError:
      if (statusCode == 404 ||
          statusCode == 403 ||
          statusCode == 409 ||
          statusCode == 422) {
        return ErrorCategory.validation;
      }
      return ErrorCategory.unexpected;
    case NetworkKind.online:
      return ErrorCategory.temporaryNetwork;
  }
}

/// Support is for unexpected errors, payment problems, persistent API outages,
/// or an explicit user "Need Help?" — never for a one-off timeout.
bool shouldOfferSupport(
  ErrorCategory category, {
  int retryCount = 0,
  bool userRequested = false,
}) {
  if (userRequested) return true;
  switch (category) {
    case ErrorCategory.unexpected:
      return true;
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

String escalationCopy(ErrorCategory category, int retryCount) {
  if (category == ErrorCategory.payment) {
    return 'Payment could not be completed.';
  }
  if (category == ErrorCategory.unexpected) {
    return "We're sorry, something unexpected happened while loading this part of GreenLeaf.";
  }
  if (retryCount <= 0) return 'Unable to refresh.';
  if (retryCount == 1) return 'Still having trouble connecting.';
  return "We're sorry, something unexpected happened while loading this part of GreenLeaf.";
}
