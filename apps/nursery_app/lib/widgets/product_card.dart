import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/app_motion.dart';
import 'package:nursery_app/widgets/mini_cart_sheet.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class ProductCard extends StatefulWidget {
  const ProductCard({super.key, required this.product});

  final ProductSummary product;

  @override
  State<ProductCard> createState() => _ProductCardState();
}

class _ProductCardState extends State<ProductCard> {
  bool _busy = false;

  ProductSummary get product => widget.product;

  AppBadge? _badge() {
    if (product.isOutOfStock) {
      return const AppBadge(label: 'Out of stock', tone: AppBadgeTone.error);
    }
    if (product.badges.contains('sale') || product.hasDiscount) {
      return const AppBadge(label: 'Sale', tone: AppBadgeTone.warning);
    }
    if (product.badges.contains('bestseller')) {
      return const AppBadge(label: 'Bestseller', tone: AppBadgeTone.brand);
    }
    if (product.badges.contains('new') ||
        product.badges.contains('new-arrival')) {
      return const AppBadge(label: 'New', tone: AppBadgeTone.success);
    }
    if (product.isLowStock || product.badges.contains('low-stock')) {
      return const AppBadge(label: 'Low stock', tone: AppBadgeTone.warning);
    }
    if (product.badges.isNotEmpty) {
      return AppBadge(
        label: product.badges.first.replaceAll('-', ' '),
        tone: AppBadgeTone.neutral,
      );
    }
    return null;
  }

  Future<void> _add() async {
    if (_busy || product.isOutOfStock) return;
    setState(() => _busy = true);
    try {
      await context.read<CartProvider>().addItem(product.id);
      if (!mounted) return;
      AppFeedback.success(
        context,
        'Added to cart',
        actionLabel: 'View',
        onAction: () => showMiniCart(context),
      );
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to add item. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _toggleWish() async {
    final auth = context.read<AuthProvider>();
    if (auth.user == null) {
      AuthNavigation.pushLogin(context, redirect: '/product/${product.slug}');
      return;
    }
    final wishlist = context.read<WishlistProvider>();
    try {
      final saved = await wishlist.toggle(product.id);
      if (!mounted) return;
      AppFeedback.success(
        context,
        saved ? 'Added to wishlist' : 'Removed from wishlist',
      );
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        'Unable to update wishlist. Please try again.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final badge = _badge();
    final wishSaved = context.select<WishlistProvider, bool>(
      (w) => w.contains(product.id),
    );

    return Semantics(
      button: true,
      label: '${product.name}, ${money(product.price)}',
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/product/${product.slug}'),
          borderRadius: BorderRadius.circular(AppRadii.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadii.lg),
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: AppColors.surfaceMuted,
                            border: Border.all(color: AppColors.border),
                            borderRadius: BorderRadius.circular(AppRadii.lg),
                          ),
                          child: product.thumbnailUrl == null
                              ? const Center(
                                  child: Icon(
                                    Icons.local_florist_outlined,
                                    color: AppColors.muted,
                                  ),
                                )
                              : CachedNetworkImage(
                                  imageUrl: product.thumbnailUrl!,
                                  fit: BoxFit.cover,
                                  memCacheWidth: 600,
                                  fadeInDuration: AppDuration.fast,
                                  placeholder: (context, url) =>
                                      Container(color: AppColors.surfaceMuted),
                                  errorWidget: (context, url, error) =>
                                      const Center(
                                        child: Icon(
                                          Icons.local_florist_outlined,
                                          color: AppColors.muted,
                                        ),
                                      ),
                                ),
                        ),
                      ),
                    ),
                    Positioned(
                      top: AppSpace.sm,
                      right: AppSpace.sm,
                      child: Material(
                        color: Colors.white.withValues(alpha: 0.94),
                        shape: const CircleBorder(),
                        child: WishlistHeart(
                          saved: wishSaved,
                          onPressed: _toggleWish,
                        ),
                      ),
                    ),
                    if (badge != null)
                      Positioned(
                        top: AppSpace.sm,
                        left: AppSpace.sm,
                        child: badge,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpace.sm),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  height: 1.25,
                  color: AppColors.ink,
                ),
              ),
              const SizedBox(height: AppSpace.xs),
              RatingRow(avg: product.ratingAvg, count: product.ratingCount),
              const SizedBox(height: AppSpace.xs),
              PriceText(
                price: product.price,
                compareAt: product.compareAtPrice,
                currency: product.currency,
              ),
              const SizedBox(height: AppSpace.sm),
              AppButton(
                label: product.isOutOfStock
                    ? 'Out of stock'
                    : _busy
                    ? 'Adding…'
                    : 'Add',
                loading: _busy,
                loadingLabel: 'Adding…',
                variant: AppButtonVariant.secondary,
                expanded: true,
                compact: true,
                onPressed: product.isOutOfStock || _busy ? null : _add,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
