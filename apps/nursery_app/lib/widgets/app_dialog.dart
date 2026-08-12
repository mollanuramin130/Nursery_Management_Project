import 'package:flutter/material.dart';
import 'package:nursery_app/widgets/app_button.dart';

/// Consistent Material dialogs for confirmations and simple alerts.
abstract final class AppDialog {
  static Future<void> alert(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'OK',
  }) {
    return showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          AppButton(
            label: confirmLabel,
            onPressed: () => Navigator.of(ctx).pop(),
            compact: true,
          ),
        ],
      ),
    );
  }

  /// Returns `true` when the user confirms.
  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'Confirm',
    String cancelLabel = 'Cancel',
    bool destructive = false,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actionsAlignment: MainAxisAlignment.end,
        actions: [
          AppButton(
            label: cancelLabel,
            variant: AppButtonVariant.tertiary,
            onPressed: () => Navigator.of(ctx).pop(false),
            compact: true,
          ),
          AppButton(
            label: confirmLabel,
            variant: destructive
                ? AppButtonVariant.danger
                : AppButtonVariant.primary,
            onPressed: () => Navigator.of(ctx).pop(true),
            compact: true,
          ),
        ],
      ),
    );
    return result == true;
  }
}
