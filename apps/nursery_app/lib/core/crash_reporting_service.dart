import 'dart:async';

/// Vendor-agnostic contract for remote crash reporting.
/// Implementations must guarantee fail-safe execution (no unhandled exceptions
/// from reporting failures) and zero transmission when disabled.
abstract interface class CrashReportingService {
  /// Whether remote telemetry is actively enabled.
  bool get isEnabled;

  /// Report an unexpected exception with diagnostic context.
  /// [isFatal] indicates an unhandled/fatal crash (e.g. Zone or FlutterError).
  Future<void> recordCrash(
    Object error,
    StackTrace? stack, {
    required String reference,
    required String category,
    String? screen,
    String? operation,
    int? statusCode,
    bool isFatal = false,
  });

  /// Set or clear customer identifier (opaque customer ID only; never PII).
  Future<void> setUserId(String? userId);
}

/// Default safe no-op implementation used when crash reporting is disabled or in tests.
final class NoOpCrashReportingService implements CrashReportingService {
  const NoOpCrashReportingService();

  @override
  bool get isEnabled => false;

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
  }) async {}

  @override
  Future<void> setUserId(String? userId) async {}
}
