import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

enum AppButtonVariant { primary, secondary, tertiary, danger }

/// Consistent commerce button with loading + disabled states.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.loading = false,
    this.loadingLabel,
    this.icon,
    this.expanded = false,
    this.compact = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final bool loading;
  final String? loadingLabel;
  final IconData? icon;
  final bool expanded;
  final bool compact;

  bool get _enabled => onPressed != null && !loading;

  /// While loading we keep the button visually primary (white on green) but
  /// non-interactive — Material disabled styles wash text into low contrast.
  ButtonStyle? _primaryLoadingOverride() {
    if (!loading) return null;
    return FilledButton.styleFrom(
      backgroundColor: AppColors.primaryDeep,
      foregroundColor: Colors.white,
      disabledBackgroundColor: AppColors.primaryDeep,
      disabledForegroundColor: Colors.white,
    );
  }

  ButtonStyle? _dangerLoadingOverride() {
    if (!loading) return null;
    return FilledButton.styleFrom(
      backgroundColor: AppColors.error,
      foregroundColor: Colors.white,
      disabledBackgroundColor: AppColors.error,
      disabledForegroundColor: Colors.white,
    );
  }

  @override
  Widget build(BuildContext context) {
    final child = _buildChild();
    // Compact still meets 48dp min height for Play accessibility.
    final size = Size(compact ? 0 : AppTouch.min, AppTouch.min);

    Widget button;
    switch (variant) {
      case AppButtonVariant.primary:
        button = FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(minimumSize: size)
              .merge(_primaryLoadingOverride()),
          child: child,
        );
      case AppButtonVariant.secondary:
        button = OutlinedButton(
          onPressed: _enabled ? onPressed : null,
          style: OutlinedButton.styleFrom(
            minimumSize: size,
            foregroundColor: AppColors.primaryDeep,
            disabledForegroundColor: loading
                ? AppColors.primaryDeep
                : AppColors.muted,
          ),
          child: child,
        );
      case AppButtonVariant.tertiary:
        button = TextButton(
          onPressed: _enabled ? onPressed : null,
          style: TextButton.styleFrom(
            minimumSize: size,
            foregroundColor: AppColors.primaryDeep,
            disabledForegroundColor: loading
                ? AppColors.primaryDeep
                : AppColors.muted,
          ),
          child: child,
        );
      case AppButtonVariant.danger:
        button = FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(
            minimumSize: size,
            backgroundColor: AppColors.error,
            foregroundColor: Colors.white,
            disabledBackgroundColor: loading
                ? AppColors.error
                : AppColors.errorSoft,
            disabledForegroundColor: loading
                ? Colors.white
                : AppColors.error.withValues(alpha: 0.5),
          ).merge(_dangerLoadingOverride()),
          child: child,
        );
    }

    if (expanded) {
      return SizedBox(width: double.infinity, child: button);
    }
    return button;
  }

  Widget _buildChild() {
    final Color? forcedFg = switch (variant) {
      AppButtonVariant.primary || AppButtonVariant.danger => Colors.white,
      _ => null,
    };

    if (loading) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color:
                  variant == AppButtonVariant.secondary ||
                      variant == AppButtonVariant.tertiary
                  ? AppColors.primary
                  : Colors.white,
            ),
          ),
          const SizedBox(width: AppSpace.sm),
          Text(
            loadingLabel ?? label,
            style: forcedFg == null ? null : TextStyle(color: forcedFg),
          ),
        ],
      );
    }

    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 18, color: forcedFg),
          const SizedBox(width: AppSpace.sm),
          Text(
            label,
            style: forcedFg == null ? null : TextStyle(color: forcedFg),
          ),
        ],
      );
    }

    return Text(
      label,
      style: forcedFg == null ? null : TextStyle(color: forcedFg),
    );
  }
}
