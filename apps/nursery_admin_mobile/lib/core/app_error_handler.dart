import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:nursery_admin_mobile/core/app_error.dart';
import 'package:nursery_admin_mobile/core/support.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';
import 'package:nursery_admin_mobile/widgets/greenleaf_error_view.dart';

class AppErrorRecord {
  AppErrorRecord({
    required this.category,
    required this.reference,
    required this.at,
    this.screen,
    this.statusCode,
  });

  final ErrorCategory category;
  final String reference;
  final DateTime at;
  final String? screen;
  final int? statusCode;
}

class AppErrorHandler {
  static final List<AppErrorRecord> records = <AppErrorRecord>[];
  static VoidCallback? onRebuildRequested;

  static AppErrorRecord record(
    Object error,
    StackTrace? stack, {
    ErrorCategory category = ErrorCategory.unexpected,
    String? screen,
    int? statusCode,
  }) {
    final rec = AppErrorRecord(
      category: category,
      reference: newErrorReference(),
      at: DateTime.now(),
      screen: screen,
      statusCode: statusCode,
    );
    records.add(rec);
    if (records.length > 50) records.removeAt(0);
    if (kDebugMode) {
      debugPrint(
        '[GL-ERR] ${rec.reference} ${rec.category.name} '
        '${redactSecrets(error.toString())}',
      );
    }
    return rec;
  }
}

void installAppErrorHandling() {
  FlutterError.onError = (details) {
    AppErrorHandler.record(
      details.exception,
      details.stack,
      screen: 'flutter_error',
    );
    if (kDebugMode) FlutterError.presentError(details);
  };

  PlatformDispatcher.instance.onError = (error, stack) {
    AppErrorHandler.record(error, stack, screen: 'platform');
    return true;
  };

  ErrorWidget.builder = (details) {
    final rec = AppErrorHandler.record(
      details.exception,
      details.stack,
      screen: 'widget',
    );
    return Material(
      color: OpsColors.surface,
      child: SafeArea(
        child: GreenLeafErrorView(
          reference: rec.reference,
          onRetry: AppErrorHandler.onRebuildRequested,
          operational: true,
        ),
      ),
    );
  };
}
