import 'package:flutter/material.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/mini_cart_sheet.dart';
import 'package:provider/provider.dart';

/// Cart icon that badges from [CartProvider] and opens mini-cart.
class CartIconButton extends StatelessWidget {
  const CartIconButton({
    super.key,
    this.onPressed,
    this.tooltip = 'Open cart',
    this.openMiniCart = true,
  });

  final VoidCallback? onPressed;
  final String tooltip;
  final bool openMiniCart;

  @override
  Widget build(BuildContext context) {
    final count = context.watch<CartProvider>().cart.itemCount;

    return IconButton(
      tooltip: tooltip,
      onPressed:
          onPressed ??
          () {
            if (openMiniCart) {
              showMiniCart(context);
            }
          },
      icon: Badge(
        isLabelVisible: count > 0,
        label: Text(
          count > 99 ? '99+' : '$count',
          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
        ),
        backgroundColor: AppColors.sale,
        child: const Icon(Icons.shopping_bag_outlined),
      ),
    );
  }
}
