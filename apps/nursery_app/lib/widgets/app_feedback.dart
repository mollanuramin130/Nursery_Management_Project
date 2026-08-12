import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

enum AppFeedbackTone { success, error, info }

/// Consistent snackbar feedback — prefer this over ad-hoc SnackBars.
abstract final class AppFeedback {
  static void show(
    BuildContext context, {
    required String message,
    AppFeedbackTone tone = AppFeedbackTone.success,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    final messenger = ScaffoldMessenger.of(context);
    messenger.hideCurrentSnackBar();

    final bg = switch (tone) {
      AppFeedbackTone.success => AppColors.primaryDeep,
      AppFeedbackTone.error => AppColors.error,
      AppFeedbackTone.info => AppColors.inkSoft,
    };

    final icon = switch (tone) {
      AppFeedbackTone.success => Icons.check_circle_outline_rounded,
      AppFeedbackTone.error => Icons.error_outline_rounded,
      AppFeedbackTone.info => Icons.info_outline_rounded,
    };

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: bg,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(
          AppSpace.lg,
          0,
          AppSpace.lg,
          AppSpace.lg,
        ),
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: AppSpace.sm),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
        action: actionLabel != null && onAction != null
            ? SnackBarAction(
                label: actionLabel,
                textColor: Colors.white,
                onPressed: onAction,
              )
            : null,
      ),
    );
  }

  static void success(
    BuildContext context,
    String message, {
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    show(
      context,
      message: message,
      tone: AppFeedbackTone.success,
      actionLabel: actionLabel,
      onAction: onAction,
    );
  }

  static void error(BuildContext context, String message) {
    show(context, message: _friendly(message), tone: AppFeedbackTone.error);
  }

  static void info(BuildContext context, String message) {
    show(context, message: message, tone: AppFeedbackTone.info);
  }

  /// Never surface Dio/stack traces to customers.
  static String _friendly(String raw) {
    final lower = raw.toLowerCase();
    if (lower.contains('socket') ||
        lower.contains('network') ||
        lower.contains('timed out') ||
        lower.contains('connection')) {
      return 'Please check your connection and try again.';
    }
    if (lower.contains('dio') ||
        lower.contains('exception') ||
        lower.contains('stack') ||
        lower.contains('http') ||
        lower.contains('status code')) {
      return 'Something went wrong. Please try again.';
    }
    // Trim overly long API dumps
    if (raw.length > 120) {
      return 'Something went wrong. Please try again.';
    }
    return raw;
  }
}
