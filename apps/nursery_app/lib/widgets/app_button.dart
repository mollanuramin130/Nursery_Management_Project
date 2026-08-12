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
          style: FilledButton.styleFrom(minimumSize: size),
          child: child,
        );
      case AppButtonVariant.secondary:
        button = OutlinedButton(
          onPressed: _enabled ? onPressed : null,
          style: OutlinedButton.styleFrom(minimumSize: size),
          child: child,
        );
      case AppButtonVariant.tertiary:
        button = TextButton(
          onPressed: _enabled ? onPressed : null,
          style: TextButton.styleFrom(minimumSize: size),
          child: child,
        );
      case AppButtonVariant.danger:
        button = FilledButton(
          onPressed: _enabled ? onPressed : null,
          style: FilledButton.styleFrom(
            minimumSize: size,
            backgroundColor: AppColors.error,
            foregroundColor: Colors.white,
            disabledBackgroundColor: AppColors.errorSoft,
            disabledForegroundColor: AppColors.error.withValues(alpha: 0.5),
          ),
          child: child,
        );
    }

    if (expanded) {
      return SizedBox(width: double.infinity, child: button);
    }
    return button;
  }

  Widget _buildChild() {
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
          Text(loadingLabel ?? label),
        ],
      );
    }

    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 18),
          const SizedBox(width: AppSpace.sm),
          Text(label),
        ],
      );
    }

    return Text(label);
  }
}
