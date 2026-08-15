import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_bottom_sheet.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:provider/provider.dart';

/// Lightweight cart preview sheet — full cart remains the tab.
Future<void> showMiniCart(BuildContext context) {
  return AppBottomSheet.show<void>(
    context: context,
    title: 'Your cart',
    child: const _MiniCartBody(),
    actions: const _MiniCartActions(),
  );
}

class _MiniCartBody extends StatelessWidget {
  const _MiniCartBody();

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>().cart;
    final loading = context.watch<CartProvider>().loading;

    if (loading && cart.items.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: AppSpace.xxl),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (cart.items.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpace.xl),
        child: Column(
          children: [
            const Icon(
              Icons.shopping_bag_outlined,
              size: 40,
              color: AppColors.muted,
            ),
            const SizedBox(height: AppSpace.md),
            Text(
              'Your cart is empty',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: AppSpace.xs),
            Text(
              'Add plants to see them here.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      );
    }

    final preview = cart.items.take(4).toList();
    return Column(
      children: [
        ...preview.map((item) {
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpace.md),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadii.md),
                  child: ResilientNetworkImage(
                    url: item.thumbnailUrl,
                    width: 56,
                    height: 56,
                    fit: BoxFit.cover,
                  ),
                ),
                const SizedBox(width: AppSpace.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        'Qty ${item.quantity} · ${money(item.lineTotal)}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        }),
        if (cart.items.length > 4)
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              '+${cart.items.length - 4} more in cart',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        const SizedBox(height: AppSpace.sm),
        Row(
          children: [
            Text('Subtotal', style: Theme.of(context).textTheme.titleSmall),
            const Spacer(),
            Text(
              money(cart.subtotal),
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppColors.primaryDeep,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        if (cart.discountTotal > 0) ...[
          const SizedBox(height: AppSpace.xs),
          Row(
            children: [
              Text('Discount', style: Theme.of(context).textTheme.bodySmall),
              const Spacer(),
              Text(
                '-${money(cart.discountTotal)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ],
        if (cart.freeDelivery.enabled && !cart.freeDelivery.qualifies) ...[
          const SizedBox(height: AppSpace.sm),
          Text(
            'Add ${money(cart.freeDelivery.remaining)} more for free delivery',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.primaryDeep),
          ),
        ],
      ],
    );
  }
}

class _MiniCartActions extends StatelessWidget {
  const _MiniCartActions();

  @override
  Widget build(BuildContext context) {
    final empty = context.watch<CartProvider>().cart.items.isEmpty;
    return Row(
      children: [
        Expanded(
          child: AppButton(
            label: empty ? 'Browse plants' : 'View cart',
            variant: AppButtonVariant.secondary,
            onPressed: () {
              Navigator.pop(context);
              if (empty) {
                context.push('/catalog');
              } else {
                context.go('/cart');
              }
            },
          ),
        ),
        if (!empty) ...[
          const SizedBox(width: AppSpace.sm),
          Expanded(
            child: AppButton(
              label: 'Checkout',
              onPressed: () {
                Navigator.pop(context);
                final signedIn = context.read<AuthProvider>().user != null;
                if (!signedIn) {
                  AuthNavigation.pushLogin(context, redirect: '/checkout');
                  return;
                }
                context.push('/checkout');
              },
            ),
          ),
        ],
      ],
    );
  }
}
