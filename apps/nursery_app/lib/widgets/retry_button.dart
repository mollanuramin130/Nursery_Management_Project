import 'package:flutter/material.dart';
import 'package:nursery_app/widgets/app_button.dart';

/// Debounced retry control. Ignores taps while the previous retry is running.
class RetryButton extends StatefulWidget {
  const RetryButton({
    super.key,
    this.label = 'Try again',
    this.onPressed,
    this.expanded = false,
    this.icon = Icons.refresh_rounded,
    this.variant = AppButtonVariant.primary,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool expanded;
  final IconData? icon;
  final AppButtonVariant variant;

  @override
  State<RetryButton> createState() => _RetryButtonState();
}

class _RetryButtonState extends State<RetryButton> {
  bool _busy = false;

  Future<void> _run() async {
    if (_busy || widget.onPressed == null) return;
    setState(() => _busy = true);
    try {
      widget.onPressed!();
      await Future<void>.delayed(const Duration(milliseconds: 450));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppButton(
      label: widget.label,
      onPressed: widget.onPressed == null ? null : _run,
      loading: _busy,
      expanded: widget.expanded,
      icon: widget.icon,
      variant: widget.variant,
    );
  }
}
