import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Shared bottom-sheet chrome for filters, sort, coupons, etc.
class AppBottomSheet extends StatelessWidget {
  const AppBottomSheet({
    super.key,
    required this.title,
    required this.child,
    this.actions,
    this.showHandle = true,
    this.maxHeightFactor = 0.88,
  });

  final String title;
  final Widget child;
  final Widget? actions;
  final bool showHandle;
  final double maxHeightFactor;

  /// Present a standardized sheet. Returns the popped result.
  static Future<T?> show<T>({
    required BuildContext context,
    required String title,
    required Widget child,
    Widget? actions,
    bool isScrollControlled = true,
    bool showHandle = true,
  }) {
    return showModalBottomSheet<T>(
      context: context,
      isScrollControlled: isScrollControlled,
      backgroundColor: AppColors.surface,
      barrierColor: AppColors.ink.withValues(alpha: 0.35),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.xxl)),
      ),
      builder: (ctx) => AppBottomSheet(
        title: title,
        showHandle: showHandle,
        actions: actions,
        child: child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final maxH = media.size.height * maxHeightFactor;

    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxH),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (showHandle) ...[
              const SizedBox(height: AppSpace.sm),
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.borderStrong,
                  borderRadius: BorderRadius.circular(AppRadii.full),
                ),
              ),
            ],
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpace.lg,
                AppSpace.md,
                AppSpace.sm,
                AppSpace.sm,
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(AppSpace.lg),
                child: child,
              ),
            ),
            if (actions != null) ...[
              const Divider(height: 1),
              Padding(
                padding: EdgeInsets.fromLTRB(
                  AppSpace.lg,
                  AppSpace.md,
                  AppSpace.lg,
                  AppSpace.md + media.viewInsets.bottom,
                ),
                child: actions!,
              ),
            ] else
              SizedBox(height: media.viewInsets.bottom),
          ],
        ),
      ),
    );
  }
}

/// Convenience action row for sheet footers: Clear + Apply.
class AppBottomSheetActions extends StatelessWidget {
  const AppBottomSheetActions({
    super.key,
    required this.primaryLabel,
    required this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
    this.primaryLoading = false,
  });

  final String primaryLabel;
  final VoidCallback? onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;
  final bool primaryLoading;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (secondaryLabel != null) ...[
          Expanded(
            child: OutlinedButton(
              onPressed: onSecondary,
              child: Text(secondaryLabel!),
            ),
          ),
          const SizedBox(width: AppSpace.sm),
        ],
        Expanded(
          child: FilledButton(
            onPressed: primaryLoading ? null : onPrimary,
            child: Text(primaryLoading ? '…' : primaryLabel),
          ),
        ),
      ],
    );
  }
}
