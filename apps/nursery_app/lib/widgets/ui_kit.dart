import 'package:flutter/material.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';

class AppBadge extends StatelessWidget {
  const AppBadge({
    super.key,
    required this.label,
    this.tone = AppBadgeTone.brand,
  });

  final String label;
  final AppBadgeTone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      AppBadgeTone.brand => (AppColors.primarySoft, AppColors.primaryDeep),
      AppBadgeTone.success => (AppColors.successSoft, AppColors.success),
      AppBadgeTone.warning => (AppColors.warningSoft, AppColors.warning),
      AppBadgeTone.error => (AppColors.errorSoft, AppColors.error),
      AppBadgeTone.sale => (AppColors.sale, Colors.white),
      AppBadgeTone.neutral => (AppColors.surfaceMuted, AppColors.inkSoft),
    };

    return Semantics(
      label: label,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(AppRadii.sm),
        ),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            color: fg,
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.4,
          ),
        ),
      ),
    );
  }
}

enum AppBadgeTone { brand, success, warning, error, sale, neutral }

class PriceText extends StatelessWidget {
  const PriceText({
    super.key,
    required this.price,
    this.compareAt,
    this.currency = 'INR',
    this.large = false,
  });

  final double price;
  final double? compareAt;
  final String currency;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final hasDiscount = compareAt != null && compareAt! > price;
    final pct = hasDiscount
        ? (((compareAt! - price) / compareAt!) * 100).round()
        : null;

    return Semantics(
      label: hasDiscount
          ? '${money(price, currency)}, was ${money(compareAt!, currency)}, $pct percent off'
          : money(price, currency),
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 6,
        runSpacing: 2,
        children: [
          Text(
            money(price, currency),
            style: TextStyle(
              fontSize: large ? 22 : 15,
              fontWeight: FontWeight.w800,
              color: AppColors.primaryDeep,
              height: 1.2,
            ),
          ),
          if (hasDiscount)
            Text(
              money(compareAt!, currency),
              style: TextStyle(
                fontSize: large ? 14 : 12,
                color: AppColors.muted,
                decoration: TextDecoration.lineThrough,
              ),
            ),
          if (pct != null)
            Text(
              '$pct% OFF',
              style: TextStyle(
                fontSize: large ? 13 : 11,
                fontWeight: FontWeight.w800,
                color: AppColors.sale,
              ),
            ),
        ],
      ),
    );
  }
}

class RatingRow extends StatelessWidget {
  const RatingRow({super.key, this.avg, this.count});

  final double? avg;
  final int? count;

  @override
  Widget build(BuildContext context) {
    if (avg == null || avg! <= 0) return const SizedBox.shrink();
    return Semantics(
      label:
          'Rated ${avg!.toStringAsFixed(1)} out of 5'
          '${count != null && count! > 0 ? ', $count reviews' : ''}',
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, size: 16, color: AppColors.rating),
          const SizedBox(width: 2),
          Text(
            avg!.toStringAsFixed(1),
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: AppColors.inkSoft,
            ),
          ),
          if (count != null && count! > 0)
            Text(
              ' ($count)',
              style: const TextStyle(fontSize: 12, color: AppColors.muted),
            ),
        ],
      ),
    );
  }
}

class EmptyStateView extends StatelessWidget {
  const EmptyStateView({
    super.key,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
    this.secondaryActionLabel,
    this.onSecondaryAction,
    this.icon = Icons.local_florist_outlined,
  });

  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final String? secondaryActionLabel;
  final VoidCallback? onSecondaryAction;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpace.xxl),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: const BoxDecoration(
                  color: AppColors.primarySoft,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 36, color: AppColors.primaryDeep),
              ),
              const SizedBox(height: AppSpace.xl),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: AppSpace.sm),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              if (actionLabel != null && onAction != null) ...[
                const SizedBox(height: AppSpace.xl),
                AppButton(
                  label: actionLabel!,
                  onPressed: onAction,
                  expanded: true,
                ),
              ],
              if (secondaryActionLabel != null &&
                  onSecondaryAction != null) ...[
                const SizedBox(height: AppSpace.sm),
                AppButton(
                  label: secondaryActionLabel!,
                  onPressed: onSecondaryAction,
                  variant: AppButtonVariant.tertiary,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class ErrorStateView extends StatelessWidget {
  const ErrorStateView({
    super.key,
    this.title = 'Unable to load',
    this.message =
        'Something went wrong while connecting to the nursery. Please try again.',
    this.onRetry,
  });

  final String title;
  final String message;
  final VoidCallback? onRetry;

  static String sanitize(String? raw) {
    if (raw == null || raw.trim().isEmpty) {
      return 'Something went wrong while connecting to the nursery. Please try again.';
    }
    final lower = raw.toLowerCase();
    if (lower.contains('exception') ||
        lower.contains('dio') ||
        lower.contains('socket') ||
        lower.contains('http') ||
        lower.contains('status code') ||
        lower.contains('stack') ||
        raw.length > 140) {
      return 'Something went wrong while connecting to the nursery. Please try again.';
    }
    return raw;
  }

  @override
  Widget build(BuildContext context) {
    final safeMessage = sanitize(message);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpace.xxl),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: const BoxDecoration(
                  color: AppColors.errorSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.cloud_off_rounded,
                  color: AppColors.error,
                  size: 36,
                ),
              ),
              const SizedBox(height: AppSpace.xl),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: AppSpace.sm),
              Text(
                safeMessage,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              if (onRetry != null) ...[
                const SizedBox(height: AppSpace.xl),
                AppButton(
                  label: 'Try again',
                  onPressed: onRetry,
                  icon: Icons.refresh_rounded,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class SkeletonBox extends StatefulWidget {
  const SkeletonBox({
    super.key,
    this.height = 16,
    this.width,
    this.radius = AppRadii.md,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  State<SkeletonBox> createState() => _SkeletonBoxState();
}

class _SkeletonBoxState extends State<SkeletonBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.disableAnimationsOf(context);

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = reduce ? 0.5 : _controller.value;
        return Opacity(opacity: 0.45 + (t * 0.45), child: child);
      },
      child: Container(
        height: widget.height,
        width: widget.width,
        decoration: BoxDecoration(
          color: AppColors.surfaceMuted,
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

class ProductCardSkeleton extends StatelessWidget {
  const ProductCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AspectRatio(
          aspectRatio: 4 / 5,
          child: SkeletonBox(height: double.infinity, radius: AppRadii.lg),
        ),
        SizedBox(height: AppSpace.sm),
        SkeletonBox(height: 14, width: 120),
        SizedBox(height: 6),
        SkeletonBox(height: 14, width: 72),
        SizedBox(height: AppSpace.sm),
        SkeletonBox(height: 40, radius: AppRadii.full),
      ],
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpace.screen,
        AppSpace.xxl,
        AppSpace.screen,
        AppSpace.sm,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.headlineMedium),
                if (subtitle != null) ...[
                  const SizedBox(height: AppSpace.xs),
                  Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
                ],
              ],
            ),
          ),
          if (actionLabel != null && onAction != null)
            TextButton(onPressed: onAction, child: Text(actionLabel!)),
        ],
      ),
    );
  }
}

/// Surface card used for addresses, delivery, orders, etc.
class AppSurfaceCard extends StatelessWidget {
  const AppSurfaceCard({
    super.key,
    required this.child,
    this.onTap,
    this.selected = false,
    this.padding = const EdgeInsets.all(AppSpace.md),
    this.margin,
  });

  final Widget child;
  final VoidCallback? onTap;
  final bool selected;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    final card = AnimatedContainer(
      duration: AppDuration.fast,
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: selected ? AppColors.primarySoft : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        border: Border.all(
          color: selected ? AppColors.primary : AppColors.border,
          width: selected ? 2 : 1,
        ),
      ),
      child: child,
    );

    if (onTap == null) return card;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        child: card,
      ),
    );
  }
}

/// Quantity stepper used in cart / PDP.
class QtySelector extends StatelessWidget {
  const QtySelector({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max = 99,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Quantity $value',
      child: Container(
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadii.full),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              tooltip: 'Decrease quantity',
              constraints: const BoxConstraints(
                minWidth: AppTouch.min,
                minHeight: AppTouch.min,
              ),
              onPressed: value <= min ? null : () => onChanged(value - 1),
              icon: const Icon(Icons.remove_rounded, size: 20),
            ),
            ConstrainedBox(
              constraints: const BoxConstraints(minWidth: 28),
              child: Text(
                '$value',
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            IconButton(
              tooltip: 'Increase quantity',
              constraints: const BoxConstraints(
                minWidth: AppTouch.min,
                minHeight: AppTouch.min,
              ),
              onPressed: value >= max ? null : () => onChanged(value + 1),
              icon: const Icon(Icons.add_rounded, size: 20),
            ),
          ],
        ),
      ),
    );
  }
}
