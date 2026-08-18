import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:nursery_app/core/app_error.dart';
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
}

String redactSecrets(String raw) {
  var out = raw;
  out = out.replaceAll(
    RegExp(r'eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'),
    '[redacted]',
  );
  out = out.replaceAll(
    RegExp(
      r'(password|passwd|secret|token|jwt|cookie|authorization|razorpay[_-]?key|webhook[_-]?secret)[=:]\s*\S+',
      caseSensitive: false,
    ),
    r'$1=[redacted]',
  );
  out = out.replaceAll(
    RegExp(r'rzp_(live|test)_[A-Za-z0-9]+'),
    '[redacted]',
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
