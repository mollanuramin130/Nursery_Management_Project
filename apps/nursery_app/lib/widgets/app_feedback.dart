import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/shell_nav_policy.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/sticky_commerce_bar.dart';

enum AppFeedbackTone { success, error, info }

/// Consistent snackbar feedback — prefer this over ad-hoc SnackBars.
abstract final class AppFeedback {
  static double _bottomClearance(BuildContext context) {
    final inset = MediaQuery.viewPaddingOf(context).bottom;
    final router = GoRouter.maybeOf(context);
    final path = router?.routeInformationProvider.value.uri.path ?? '';
    // Fullscreen PDP/checkout: only clear sticky commerce bar.
    if (path.isNotEmpty && ShellNavPolicy.isFullscreenLocation(path)) {
      return inset + StickyCommerceBar.clearance;
    }
    // Shell tabs: clear NavigationBar; cart also has sticky Checkout above tabs.
    var clearance = inset + ShellNavPolicy.tabBarHeight + 16;
    if (path.isNotEmpty && ShellNavPolicy.shellHasStickyCommerce(path)) {
      clearance += StickyCommerceBar.clearance;
    }
    return clearance;
  }

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

    final label = actionLabel == null
        ? null
        : (actionLabel.length > 8 ? actionLabel.substring(0, 8) : actionLabel);

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: bg,
        behavior: SnackBarBehavior.floating,
        margin: EdgeInsets.fromLTRB(
          AppSpace.lg,
          0,
          AppSpace.lg,
          AppSpace.sm + _bottomClearance(context),
        ),
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: AppSpace.sm),
            Expanded(
              child: Text(
                message,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
        action: label != null && onAction != null
            ? SnackBarAction(
                label: label,
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
