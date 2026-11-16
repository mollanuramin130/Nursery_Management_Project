import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/app_error_handler.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/crash_reporting_service.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

/// Production Sentry implementation of [CrashReportingService].
/// Configured securely via `--dart-define=SENTRY_DSN=...`.
/// If DSN is empty or omitted, remote delivery is completely disabled (no-op).
class SentryCrashReportingService implements CrashReportingService {
  SentryCrashReportingService({
    String? dsn,
    bool? enableInDebug,
  })  : _dsn = dsn ?? const String.fromEnvironment('SENTRY_DSN'),
        _enableInDebug = enableInDebug ??
            const bool.fromEnvironment('SENTRY_ENABLE_IN_DEBUG',
                defaultValue: false);

  final String _dsn;
  final bool _enableInDebug;
  bool _initialized = false;

  @override
  bool get isEnabled =>
      _dsn.trim().isNotEmpty && (!kDebugMode || _enableInDebug);

  /// Initialize Sentry SDK when enabled. Never throws; fail-safe.
  Future<void> init() async {
    if (!isEnabled || _initialized) return;
    try {
      await SentryFlutter.init(
        (options) {
          options.dsn = _dsn.trim();
          options.environment = kReleaseMode
              ? 'production'
              : (kProfileMode ? 'profile' : 'development');
          options.release = '${AppConfig.platform}@${AppConfig.appVersion}';

          // Critical privacy & security controls:
          options.sendDefaultPii = false;
          options.enableAutoSessionTracking = false;

          // DUP-01: Remove auto-integrations that duplicate AppErrorHandler's authoritative capture:
          for (final integration in options.integrations) {
            final name = integration.runtimeType.toString();
            if (name == 'FlutterErrorIntegration' ||
                name == 'OnErrorIntegration') {
              options.removeIntegration(integration);
            }
          }

          // Event-level privacy filtering & PII redaction:
          options.beforeSend = (event, hint) => sanitizeSentryEvent(event);
        },
      );
      _initialized = true;
    } catch (_) {
      // Telemetry initialization failure must never crash application startup
    }
  }

  @override
  Future<void> recordCrash(
    Object error,
    StackTrace? stack, {
    required String reference,
    required String category,
    String? screen,
    String? operation,
    int? statusCode,
    bool isFatal = false,
  }) async {
    if (!isEnabled) return;
    try {
      await Sentry.captureException(
        error,
        stackTrace: stack,
        withScope: (scope) {
          scope.setTag('error_reference', reference);
          scope.setTag('error_category', category);
          if (screen != null) scope.setTag('screen', screen);
          if (operation != null) scope.setTag('operation', operation);
          if (statusCode != null) {
            scope.setTag('status_code', statusCode.toString());
          }
          scope.level = isFatal ? SentryLevel.fatal : SentryLevel.error;
        },
      );
    } catch (_) {
      // Reporting failure must never throw or disrupt the application
    }
  }

  @override
  Future<void> setUserId(String? userId) async {
    if (!isEnabled) return;
    try {
      if (userId == null) {
        await Sentry.configureScope((scope) => scope.setUser(null));
      } else {
        // Only opaque user IDs (e.g. integer or UUID), never email, phone, or name
        await Sentry.configureScope(
          (scope) => scope.setUser(SentryUser(id: userId)),
        );
      }
    } catch (_) {}
  }
}

/// Standalone, pure, testable sanitizer applied in Sentry `beforeSend`.
/// Strips all authentication tokens, passwords, cookies, cards, and payment data.
/// If any redaction fails, returns `null` to drop the event rather than
/// transmitting unredacted data.
SentryEvent? sanitizeSentryEvent(SentryEvent event) {
  try {
    // 1. Sanitize message
    SentryMessage? sanitizedMessage;
    if (event.message != null) {
      sanitizedMessage = SentryMessage(
        redactSecrets(event.message!.formatted),
        template: event.message!.template != null
            ? redactSecrets(event.message!.template!)
            : null,
        params: event.message!.params
            ?.map((p) => redactSecrets(p.toString()))
            .toList(),
      );
    }

    // 2. Sanitize exceptions
    final sanitizedExceptions = event.exceptions?.map((ex) {
      return SentryException(
        type: ex.type,
        value: ex.value != null ? redactSecrets(ex.value!) : null,
        module: ex.module,
        threadId: ex.threadId,
        stackTrace: ex.stackTrace,
        mechanism: ex.mechanism,
      );
    }).toList();

    // 3. Sanitize breadcrumbs (strip arbitrary data payloads, redact text)
    final sanitizedBreadcrumbs = event.breadcrumbs?.map((b) {
      return Breadcrumb(
        message: b.message != null ? redactSecrets(b.message!) : null,
        category: b.category,
        type: b.type,
        level: b.level,
        timestamp: b.timestamp,
        data: null, // Never transmit arbitrary network request/response bodies
      );
    }).toList();

    // 4. Sanitize tags: drop sensitive keys, redact all values
    Map<String, String>? sanitizedTags;
    if (event.tags != null) {
      final clean = <String, String>{};
      for (final entry in event.tags!.entries) {
        final k = entry.key.toLowerCase();
        if (k.contains('token') ||
            k.contains('auth') ||
            k.contains('password') ||
            k.contains('secret') ||
            k.contains('cookie') ||
            k.contains('key') ||
            k.contains('card') ||
            k.contains('upi')) {
          continue; // Drop sensitive tag completely
        }
        clean[entry.key] = redactSecrets(entry.value);
      }
      sanitizedTags = clean;
    }

    // 5. Construct final sanitized event
    return event.copyWith(
      message: sanitizedMessage,
      exceptions: sanitizedExceptions,
      breadcrumbs: sanitizedBreadcrumbs,
      tags: sanitizedTags,
      user: event.user != null ? SentryUser(id: event.user!.id) : null,
    );
  } catch (_) {
    // Fail-safe: if sanitization encounters any error, drop the event!
    return null;
  }
}
