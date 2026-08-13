import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/sticky_commerce_bar.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final _couponCtrl = TextEditingController();
  bool _couponBusy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetch();
    });
  }

  @override
  void dispose() {
    _couponCtrl.dispose();
    super.dispose();
  }

  Future<void> _applyCoupon() async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty || _couponBusy) return;
    setState(() => _couponBusy = true);
    try {
      await context.read<CartProvider>().applyCoupon(code);
      if (!mounted) return;
      _couponCtrl.clear();
      AppFeedback.success(context, 'Coupon applied');
    } on ApiException catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to apply coupon. Please try again.');
    } finally {
      if (mounted) setState(() => _couponBusy = false);
    }
  }

  Future<void> _removeCoupon() async {
    setState(() => _couponBusy = true);
    try {
      await context.read<CartProvider>().removeCoupon();
      if (!mounted) return;
      AppFeedback.success(context, 'Coupon removed');
    } on ApiException catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to remove coupon. Please try again.');
    } finally {
      if (mounted) setState(() => _couponBusy = false);
    }
  }

  void _goCheckout() {
    final cart = context.read<CartProvider>();
    if (cart.loading || cart.mutating) return;
    final user = context.read<AuthProvider>().user;
    if (user == null) {
      AuthNavigation.pushLogin(context, redirect: '/checkout');
      return;
    }
    if (cart.hasError && cart.cart.items.isEmpty) {
      AppFeedback.error(context, 'Refresh cart before checkout');
      return;
    }
    if (cart.cart.items.isEmpty) {
      AppFeedback.info(context, 'Your cart is empty');
      return;
    }
    if (cart.cart.checkoutBlocked) {
      AppFeedback.error(
        context,
        'Fix unavailable items in your cart before checkout',
      );
      return;
    }
    context.push('/checkout');
  }

  Future<void> _clearCart() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear cart?'),
        content: const Text('Remove all items from your cart.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Clear'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await context.read<CartProvider>().clearCart();
      if (!mounted) return;
      AppFeedback.success(context, 'Cart cleared');
    } on ApiException catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to clear cart.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<CartProvider>();
    final cart = provider.cart;
    final loading = provider.loading;
    final free = cart.freeDelivery;
    final remaining = free.remaining;
    final threshold = free.threshold <= 0 ? 1.0 : free.threshold;
    final progress = ((threshold - remaining) / threshold).clamp(0.0, 1.0);

    Widget body;
    if (loading && cart.items.isEmpty && !provider.hasError) {
      body = const CartSkeleton();
    } else if (provider.hasError && cart.items.isEmpty) {
      body = ErrorStateView(
        title: 'Unable to load cart',
        message: ErrorStateView.sanitize(provider.error),
        onRetry: () => context.read<CartProvider>().fetch(),
      );
    } else if (cart.items.isEmpty) {
      body = EmptyStateView(
        title: 'Your cart is waiting for something green',
        message:
            'Explore our plants and discover something perfect for your home.',
        actionLabel: 'Continue shopping',
        onAction: () => context.push('/catalog'),
        icon: Icons.shopping_bag_outlined,
      );
    } else {
      body = RefreshIndicator(
        onRefresh: () => context.read<CartProvider>().fetch(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpace.screen,
            AppSpace.md,
            AppSpace.screen,
            112,
          ),
          children: [
            if (provider.hasError)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpace.md),
                child: AppSurfaceCard(
                  padding: const EdgeInsets.all(AppSpace.md),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.wifi_off_rounded,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: AppSpace.sm),
                      Expanded(
                        child: Text(
                          'Showing last known cart. Pull to refresh.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            if (cart.warnings.isNotEmpty)
              ...cart.warnings.map(
                (w) => Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: AppSpace.sm),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: w.blocking
                        ? AppColors.error.withValues(alpha: 0.08)
                        : AppColors.warning.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: w.blocking ? AppColors.error : AppColors.warning,
                    ),
                  ),
                  child: Text(
                    w.message,
                    style: TextStyle(
                      color: w.blocking ? AppColors.error : AppColors.warning,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            if (free.enabled)
              Container(
                padding: const EdgeInsets.all(AppSpace.md),
                decoration: BoxDecoration(
                  color: AppColors.primarySoft,
                  borderRadius: BorderRadius.circular(AppRadii.lg),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      free.qualifies
                          ? 'You’ve unlocked free delivery'
                          : 'Add ${money(remaining)} more for free delivery',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primaryDeep,
                      ),
                    ),
                    const SizedBox(height: AppSpace.sm),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadii.full),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 6,
                        backgroundColor: Colors.white,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: AppSpace.lg),
            ...cart.items.map(
              (item) => _CartLine(item: item, mutating: provider.mutating),
            ),
            const SizedBox(height: AppSpace.sm),
            Text('Coupon', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AppSpace.sm),
            if (cart.couponCode != null && cart.couponCode!.isNotEmpty)
              AppSurfaceCard(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpace.md,
                  vertical: AppSpace.sm,
                ),
                child: Row(
                  children: [
                    const Icon(Icons.local_offer_outlined, size: 18),
                    const SizedBox(width: AppSpace.sm),
                    Expanded(
                      child: Text(
                        cart.couponCode!,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                    TextButton(
                      onPressed: _couponBusy ? null : _removeCoupon,
                      child: const Text('Remove'),
                    ),
                  ],
                ),
              )
            else
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _couponCtrl,
                      textCapitalization: TextCapitalization.characters,
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _applyCoupon(),
                      decoration: const InputDecoration(
                        hintText: 'Enter code',
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: AppSpace.sm),
                  OutlinedButton(
                    onPressed: _couponBusy ? null : _applyCoupon,
                    child: Text(_couponBusy ? '…' : 'Apply'),
                  ),
                ],
              ),
            const SizedBox(height: AppSpace.xl),
            _Totals(cart: cart),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Cart'),
        actions: [
          if (cart.items.isNotEmpty)
            TextButton(
              onPressed: provider.mutating ? null : _clearCart,
              child: const Text('Clear'),
            ),
        ],
      ),
      body: body,
      bottomNavigationBar: cart.items.isEmpty
          ? null
          : StickyCommerceBar(
              safeBottom: false,
              priceLabel:
                  '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'}',
              price: money(cart.grandTotal),
              primaryLabel: loading || provider.mutating
                  ? 'Updating…'
                  : cart.checkoutBlocked
                      ? 'Fix cart issues'
                      : 'Checkout',
              onPrimary: loading || provider.mutating || cart.checkoutBlocked
                  ? null
                  : _goCheckout,
              loading: (loading || provider.mutating) && cart.items.isNotEmpty,
              enabled: !loading &&
                  !provider.mutating &&
                  cart.items.isNotEmpty &&
                  !cart.checkoutBlocked,
            ),
    );
  }
}

class _CartLine extends StatelessWidget {
  const _CartLine({required this.item, required this.mutating});

  final CartItem item;
  final bool mutating;

  Future<void> _updateQty(BuildContext context, int v) async {
    if (mutating) return;
    try {
      await context.read<CartProvider>().updateItem(item.id, v);
    } on ApiException catch (e) {
      if (!context.mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!context.mounted) return;
      AppFeedback.error(context, 'Unable to update quantity.');
    }
  }

  Future<void> _remove(BuildContext context) async {
    if (mutating) return;
    try {
      await context.read<CartProvider>().removeItem(item.id);
    } on ApiException catch (e) {
      if (!context.mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!context.mounted) return;
      AppFeedback.error(context, 'Unable to remove item.');
    }
  }

  Future<void> _moveToWishlist(BuildContext context) async {
    if (mutating) return;
    final user = context.read<AuthProvider>().user;
    if (user == null) {
      AuthNavigation.pushLogin(context, redirect: '/cart');
      return;
    }
    try {
      await context.read<CartProvider>().moveToWishlist(item.id);
      if (!context.mounted) return;
      await context.read<WishlistProvider>().bootstrap(signedIn: true);
      if (!context.mounted) return;
      AppFeedback.success(context, 'Moved to wishlist');
    } on ApiException catch (e) {
      if (!context.mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!context.mounted) return;
      AppFeedback.error(context, 'Unable to move to wishlist.');
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppSurfaceCard(
      margin: const EdgeInsets.only(bottom: AppSpace.md),
      padding: const EdgeInsets.all(AppSpace.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.md),
            child: item.thumbnailUrl == null
                ? Container(
                    width: 72,
                    height: 72,
                    color: AppColors.surfaceMuted,
                    alignment: Alignment.center,
                    child: const Icon(
                      Icons.local_florist_outlined,
                      color: AppColors.muted,
                    ),
                  )
                : CachedNetworkImage(
                    imageUrl: item.thumbnailUrl!,
                    width: 72,
                    height: 72,
                    fit: BoxFit.cover,
                    memCacheWidth: 216,
                    errorWidget: (context, url, error) => Container(
                      width: 72,
                      height: 72,
                      color: AppColors.surfaceMuted,
                      alignment: Alignment.center,
                      child: const Icon(
                        Icons.local_florist_outlined,
                        color: AppColors.muted,
                      ),
                    ),
                  ),
          ),
          const SizedBox(width: AppSpace.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                GestureDetector(
                  onTap: item.slug == null
                      ? null
                      : () => context.push('/product/${item.slug}'),
                  child: Text(
                    item.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(height: AppSpace.xs),
                Text(
                  money(item.unitPrice),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: AppSpace.sm),
                Row(
                  children: [
                    QtySelector(
                      value: item.quantity,
                      enabled: !mutating,
                      onChanged: (v) => _updateQty(context, v),
                    ),
                    const Spacer(),
                    Text(
                      money(item.lineTotal),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpace.xs),
                Wrap(
                  spacing: AppSpace.sm,
                  children: [
                    TextButton.icon(
                      onPressed:
                          mutating ? null : () => _moveToWishlist(context),
                      icon: const Icon(Icons.favorite_border, size: 16),
                      label: const Text('Move to wishlist'),
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.primary,
                        padding: EdgeInsets.zero,
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                    TextButton(
                      onPressed: mutating ? null : () => _remove(context),
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.error,
                        padding: EdgeInsets.zero,
                        visualDensity: VisualDensity.compact,
                      ),
                      child: const Text('Remove'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Totals extends StatelessWidget {
  const _Totals({required this.cart});

  final Cart cart;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _row(context, 'Subtotal', money(cart.subtotal)),
        if (cart.discountTotal > 0)
          _row(context, 'Discount', '-${money(cart.discountTotal)}'),
        if (cart.taxTotal > 0) _row(context, 'Tax', money(cart.taxTotal)),
        if (cart.shippingTotal > 0)
          _row(context, 'Shipping', money(cart.shippingTotal)),
        const Divider(height: AppSpace.xxl),
        _row(context, 'Total', money(cart.grandTotal), bold: true),
      ],
    );
  }

  Widget _row(
    BuildContext context,
    String label,
    String value, {
    bool bold = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpace.xs),
      child: Row(
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
              color: bold ? AppColors.ink : AppColors.muted,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              fontSize: bold ? 18 : 14,
              color: bold ? AppColors.primaryDeep : AppColors.ink,
            ),
          ),
        ],
      ),
    );
  }
}
