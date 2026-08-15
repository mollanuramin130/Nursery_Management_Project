import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';

/// Sticky bottom commerce actions for PDP / cart / checkout.
class StickyCommerceBar extends StatelessWidget {
  const StickyCommerceBar({
    super.key,
    this.leading,
    this.priceLabel,
    this.price,
    this.primaryLabel,
    this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
    this.loading = false,
    this.enabled = true,

    /// When true (default), pad for system gesture inset.
    /// Set false when nested above shell NavigationBar (already padded).
    this.safeBottom = true,
  });

  final Widget? leading;
  final String? priceLabel;
  final String? price;
  final String? primaryLabel;
  final VoidCallback? onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;
  final bool loading;
  final bool enabled;
  final bool safeBottom;

  /// Approx height used by snackbar clearance on fullscreen commerce screens.
  static const double clearance = 72;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      elevation: 0,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: AppColors.surface,
          boxShadow: AppShadows.floatUp,
          border: const Border(top: BorderSide(color: AppColors.border)),
        ),
        child: SafeArea(
          top: false,
          bottom: safeBottom,
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              AppSpace.lg,
              safeBottom ? AppSpace.md : AppSpace.sm,
              AppSpace.lg,
              safeBottom ? AppSpace.md : AppSpace.sm,
            ),
            child: Row(
              children: [
                if (leading != null) ...[
                  leading!,
                  const SizedBox(width: AppSpace.md),
                ],
                if (price != null)
                  Expanded(
                    flex: 2,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (priceLabel != null)
                          Text(
                            priceLabel!,
                            style: Theme.of(context).textTheme.labelMedium,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        Text(
                          price!,
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: AppColors.primaryDeep,
                                fontWeight: FontWeight.w800,
                              ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  )
                else
                  const Spacer(),
                if (secondaryLabel != null) ...[
                  AppButton(
                    label: secondaryLabel!,
                    variant: AppButtonVariant.secondary,
                    loading: loading,
                    onPressed: enabled && !loading ? onSecondary : null,
                    compact: true,
                  ),
                  const SizedBox(width: AppSpace.sm),
                ],
                if (primaryLabel != null)
                  Flexible(
                    flex: 3,
                    child: AppButton(
                      label: primaryLabel!,
                      loading: loading,
                      loadingLabel: 'Working…',
                      onPressed: enabled && !loading ? onPrimary : null,
                      compact: true,
                      expanded: true,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
