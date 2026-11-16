import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:nursery_app/core/app_error.dart';
import 'package:nursery_app/core/crash_reporting_service.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/core/support.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/greenleaf_error_view.dart';

class AppErrorRecord {
  AppErrorRecord({
    required this.category,
    required this.reference,
    required this.at,
    this.screen,
    this.operation,
    this.statusCode,
    this.safeCode,
    this.retryCount = 0,
  });

  final ErrorCategory category;
  final String reference;
  final DateTime at;
  final String? screen;
  final String? operation;
  final int? statusCode;
  final String? safeCode;
  final int retryCount;
}

/// Central logger. Never captures passwords, JWT, cookies, or payment secrets.
class AppErrorHandler {
  static final List<AppErrorRecord> records = <AppErrorRecord>[];
  static VoidCallback? onRebuildRequested;
  static CrashReportingService crashReporter = const NoOpCrashReportingService();

  static AppErrorRecord record(
    Object error,
    StackTrace? stack, {
    ErrorCategory? category,
    String? screen,
    String? operation,
    int? statusCode,
    int retryCount = 0,
  }) {
    final resolved = category ?? _inferCategory(error, statusCode);
    final rec = AppErrorRecord(
      category: resolved,
      reference: newErrorReference(),
      at: DateTime.now(),
      screen: screen,
      operation: operation,
      statusCode: statusCode ?? _statusOf(error),
      safeCode: resolved.name,
      retryCount: retryCount,
    );
    records.add(rec);
    if (records.length > 50) {
      records.removeAt(0);
    }
    final safe = redactSecrets(error.toString());
    if (kDebugMode) {
      debugPrint(
        '[GL-ERR] ${rec.reference} ${rec.category.name} '
        'screen=${screen ?? "-"} op=${operation ?? "-"} '
        'http=${rec.statusCode ?? "-"} $safe',
      );
    }
    final isFatal =
        screen == 'flutter_error' || screen == 'platform' || screen == 'zone';
    if (crashReporter.isEnabled && _shouldReportRemotely(resolved, isFatal)) {
      unawaited(
        crashReporter
            .recordCrash(
              error,
              stack,
              reference: rec.reference,
              category: resolved.name,
              screen: screen,
              operation: operation,
              statusCode: rec.statusCode,
              isFatal: isFatal,
            )
            .catchError((_) {}),
      );
    }
    return rec;
  }

  static String userFacingMessage(Object error) {
    if (error is ClassifiedNetworkError) return error.userMessage;
    return sanitizeUserFacing(error.toString());
  }

  static ErrorCategory _inferCategory(Object error, int? statusCode) {
    if (error is ClassifiedNetworkError) {
      return categoryForNetwork(error.kind, statusCode: error.statusCode);
    }
    return ErrorCategory.unexpected;
  }

  static int? _statusOf(Object error) {
    if (error is ClassifiedNetworkError) return error.statusCode;
    return null;
  }

  static bool _shouldReportRemotely(ErrorCategory category, bool isFatal) {
    if (isFatal) return true;
    return switch (category) {
      ErrorCategory.unexpected ||
      ErrorCategory.payment ||
      ErrorCategory.apiUnavailable =>
        true,
      ErrorCategory.validation ||
      ErrorCategory.authentication ||
      ErrorCategory.temporaryNetwork ||
      ErrorCategory.rateLimited =>
        false,
    };
  }
}

/// Standard Luhn algorithm checksum verification for payment cards.
bool isValidLuhn(String digits) {
  if (digits.length < 13 || digits.length > 19) return false;
  int sum = 0;
  bool alternate = false;
  for (int i = digits.length - 1; i >= 0; i--) {
    int n = int.parse(digits[i]);
    if (alternate) {
      n *= 2;
      if (n > 9) n = (n % 10) + 1;
    }
    sum += n;
    alternate = !alternate;
  }
  return (sum % 10 == 0);
}

/// Identifies candidate strings that represent actual payment cards.
/// Distinguishes valid cards from 13-digit Unix timestamps and request IDs.
bool looksLikePaymentCard(String candidate) {
  final digits = candidate.replaceAll(RegExp(r'\D'), '');
  if (digits.length < 13 || digits.length > 19) return false;

  // Unix epoch millisecond timestamps (2023–2033) are exactly 13 continuous digits
  // starting with 16, 17, 18, 19 without spaces/hyphens.
  if (!candidate.contains(RegExp(r'[\s-]')) && digits.length == 13) {
    final prefix = digits.substring(0, 2);
    if (prefix == '16' || prefix == '17' || prefix == '18' || prefix == '19') {
      return false; // Legitimate Unix timestamp, not a payment card
    }
  }

  // Major Industry Identifiers (MII):
  // 4 = Visa, 5 = Mastercard, 3 = Amex/Diners, 6 = Discover/RuPay, 2 = Mastercard (22-27), 8 = RuPay
  final first = digits[0];
  if (first != '4' &&
      first != '5' &&
      first != '3' &&
      first != '6' &&
      first != '2' &&
      first != '8') {
    return false;
  }

  return isValidLuhn(digits);
}

String redactSecrets(String raw) {
  var out = raw;

  // 1. Redact JWT tokens
  out = out.replaceAll(
    RegExp(r'eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'),
    '[redacted]',
  );

  // 2. Redact Bearer authorization tokens
  out = out.replaceAll(
    RegExp(r'bearer\s+[A-Za-z0-9._~+/-]+=*', caseSensitive: false),
    'Bearer [redacted]',
  );

  // 3. Redact key-value secrets (plain assignment, single/double quoted JSON keys)
  final sensitiveKeyPattern = RegExp(
    r'''(?<prefix>["']?(?:password|passwd|secret|token|access_token|refresh_token|jwt|cookie|authorization|razorpay[_-]?key|webhook[_-]?secret|api[_-]?key)["']?\s*[:=]\s*)(?<val>"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|[^\s,;}{]+)''',
    caseSensitive: false,
  );
  out = out.replaceAllMapped(sensitiveKeyPattern, (m) {
    final match = m as RegExpMatch;
    final prefix = match.namedGroup('prefix') ?? '';
    final val = match.namedGroup('val') ?? '';
    if (val == '[redacted]' ||
        val == '"[redacted]"' ||
        val == "'[redacted]'" ||
        val.toLowerCase() == 'bearer') {
      return m.group(0)!;
    }
    final isDoubleQuoted = val.startsWith('"') && val.endsWith('"');
    final isSingleQuoted = val.startsWith("'") && val.endsWith("'");
    final replacement = isDoubleQuoted
        ? '"[redacted]"'
        : (isSingleQuoted ? "'[redacted]'" : '[redacted]');
    return '$prefix$replacement';
  });

  // 4. Redact Razorpay key identifiers
  out = out.replaceAll(
    RegExp(r'rzp_(live|test)_[A-Za-z0-9]+'),
    '[redacted]',
  );

  // 5. Redact payment cards using Luhn + IIN verification (eliminates timestamp false positives)
  out = out.replaceAllMapped(RegExp(r'\b(?:\d[ -]*?){13,19}\b'), (m) {
    final candidate = m.group(0)!;
    if (looksLikePaymentCard(candidate)) {
      return '[redacted_card]';
    }
    return candidate;
  });

  // 6. Redact UPI virtual payment handles
  out = out.replaceAll(
    RegExp(r'\b[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}\b'),
    '[redacted_upi]',
  );

  return out;
}

String sanitizeUserFacing(String? raw) {
  if (raw == null || raw.trim().isEmpty) {
    return 'Something went wrong. Please try again.';
  }
  final lower = raw.toLowerCase();
  if (lower.contains('password') ||
      lower.contains('authorization') ||
      lower.contains('bearer ') ||
      lower.contains('jwt') ||
      lower.contains('cookie') ||
      lower.contains('rzp_') ||
      lower.contains('stack') ||
      lower.contains('sqlstate') ||
      lower.contains('sql syntax') ||
      lower.contains('/vendor/') ||
      lower.contains('api_proxy') ||
      lower.contains('artisan') ||
      lower.contains('null check') ||
      lower.contains('bad state') ||
      lower.contains('nosuchmethod') ||
      lower.contains('typeerror') ||
      lower.contains('formatException'.toLowerCase())) {
    return 'Something went wrong. Please try again.';
  }
  if (lower.contains('exception') ||
      lower.contains('dioexception') ||
      lower.contains('php fatal')) {
    return 'Something went wrong. Please try again.';
  }
  if (raw.length > 160) return 'Something went wrong. Please try again.';
  return raw.trim();
}

void installAppErrorHandling() {
  FlutterError.onError = (details) {
    AppErrorHandler.record(
      details.exception,
      details.stack,
      screen: 'flutter_error',
      category: ErrorCategory.unexpected,
    );
    if (kDebugMode) {
      FlutterError.presentError(details);
    }
  };

  PlatformDispatcher.instance.onError = (error, stack) {
    AppErrorHandler.record(
      error,
      stack,
      screen: 'platform',
      category: ErrorCategory.unexpected,
    );
    return true;
  };

  ErrorWidget.builder = (details) {
    final rec = AppErrorHandler.record(
      details.exception,
      details.stack,
      screen: 'widget',
      category: ErrorCategory.unexpected,
    );
    return Material(
      color: AppColors.cream,
      child: SafeArea(
        child: GreenLeafErrorView(
          reference: rec.reference,
          onRetry: AppErrorHandler.onRebuildRequested,
        ),
      ),
    );
  };
}
