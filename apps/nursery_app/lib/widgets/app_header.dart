import 'package:flutter/material.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/cart_icon_button.dart';

/// Compact mobile app header for home and secondary screens.
class AppHeader extends StatelessWidget {
  const AppHeader({
    super.key,
    this.title,
    this.subtitle,
    this.leading,
    this.actions,
    this.showBack = false,
    this.onBack,
    this.bottom,
    this.deliveryLabel,
    this.onCart,
    this.centerTitle = false,
  });

  /// Home-style delivery header.
  factory AppHeader.home({
    Key? key,
    required String storeName,
    String deliveryLabel = 'Deliver to India',
    required VoidCallback onCart,
    Widget? search,
  }) {
    return AppHeader(
      key: key,
      title: storeName,
      deliveryLabel: deliveryLabel,
      onCart: onCart,
      bottom: search,
    );
  }

  /// Secondary screen with back + optional actions.
  factory AppHeader.secondary({
    Key? key,
    required String title,
    VoidCallback? onBack,
    List<Widget>? actions,
  }) {
    return AppHeader(
      key: key,
      title: title,
      showBack: true,
      onBack: onBack,
      actions: actions,
      centerTitle: false,
    );
  }

  final String? title;
  final String? subtitle;
  final Widget? leading;
  final List<Widget>? actions;
  final bool showBack;
  final VoidCallback? onBack;
  final Widget? bottom;
  final String? deliveryLabel;
  final VoidCallback? onCart;
  final bool centerTitle;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpace.sm,
            AppSpace.xs,
            AppSpace.sm,
            AppSpace.sm,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                height: AppTouch.iconButton,
                child: Row(
                  children: [
                    if (showBack || leading != null)
                      leading ??
                          IconButton(
                            tooltip: 'Go back',
                            onPressed: onBack ??
                                () => BackNavigation.toolbarBack(context),
                            icon: const Icon(Icons.arrow_back_rounded),
                          )
                    else
                      const SizedBox(width: AppSpace.sm),
                    Expanded(
                      child: deliveryLabel != null
                          ? _DeliveryTitle(
                              title: title ?? '',
                              deliveryLabel: deliveryLabel!,
                            )
                          : Text(
                              title ?? '',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              textAlign: centerTitle
                                  ? TextAlign.center
                                  : TextAlign.start,
                              style: Theme.of(context).textTheme.titleLarge
                                  ?.copyWith(color: AppColors.primaryDeep),
                            ),
                    ),
                    if (onCart != null)
                      CartIconButton(onPressed: onCart!)
                    else if (actions != null)
                      ...actions!
                    else
                      const SizedBox(width: AppSpace.sm),
                  ],
                ),
              ),
              if (subtitle != null)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: AppSpace.md),
                  child: Text(
                    subtitle!,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              if (bottom != null) ...[
                const SizedBox(height: AppSpace.sm),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: AppSpace.sm),
                  child: bottom!,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DeliveryTitle extends StatelessWidget {
  const _DeliveryTitle({required this.title, required this.deliveryLabel});

  final String title;
  final String deliveryLabel;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: AppColors.primaryDeep,
            fontWeight: FontWeight.w800,
          ),
        ),
        Row(
          children: [
            const Icon(
              Icons.location_on_outlined,
              size: 14,
              color: AppColors.muted,
            ),
            const SizedBox(width: 2),
            Flexible(
              child: Text(
                deliveryLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
