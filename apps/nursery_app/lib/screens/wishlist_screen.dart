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
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class WishlistScreen extends StatefulWidget {
  const WishlistScreen({super.key});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  Future<List<WishlistRow>>? _future;
  final Set<int> _busyIds = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  void _reload() {
    final auth = context.read<AuthProvider>();
    if (auth.user == null) {
      setState(() => _future = null);
      return;
    }
    setState(() {
      _future = context.read<ApiClient>().getData(
        '/wishlist',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => WishlistRow.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    });
    context.read<WishlistProvider>().bootstrap(signedIn: true);
  }

  Future<void> _moveToCart(WishlistRow row) async {
    final productId = row.product.id;
    if (_busyIds.contains(productId)) return;
    setState(() => _busyIds.add(productId));
    try {
      final cart = await context.read<WishlistProvider>().moveToCart(productId);
      if (!mounted) return;
      context.read<CartProvider>().replaceCart(cart);
      AppFeedback.success(context, 'Moved to cart');
      _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to move to cart.');
    } finally {
      if (mounted) setState(() => _busyIds.remove(productId));
    }
  }

  Future<void> _remove(WishlistRow row) async {
    final productId = row.product.id;
    if (_busyIds.contains(productId)) return;
    setState(() => _busyIds.add(productId));
    try {
      await context.read<WishlistProvider>().remove(productId);
      if (!mounted) return;
      AppFeedback.success(context, 'Removed from wishlist');
      _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to remove item.');
    } finally {
      if (mounted) setState(() => _busyIds.remove(productId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;

    return Scaffold(
      appBar: AppBar(title: const Text('Wishlist')),
      body: user == null
          ? EmptyStateView(
              title: 'Sign in to save plants',
              message: 'Your wishlist stays with your account.',
              actionLabel: 'Sign in',
              onAction: () =>
                  AuthNavigation.pushLogin(context, redirect: '/wishlist'),
              icon: Icons.favorite_border_rounded,
            )
          : FutureBuilder<List<WishlistRow>>(
              future: _future,
              builder: (context, snap) {
                if (_future == null ||
                    snap.connectionState != ConnectionState.done) {
                  return const ProductGridSkeleton(count: 4);
                }
                if (snap.hasError) {
                  return ErrorStateView(
                    title: 'Unable to load wishlist',
                    message: ErrorStateView.sanitize(snap.error?.toString()),
                    onRetry: _reload,
                  );
                }
                final items = (snap.data ?? [])
                    .where(
                      (row) => context.watch<WishlistProvider>().contains(
                        row.product.id,
                      ),
                    )
                    .toList();
                if (items.isEmpty) {
                  return EmptyStateView(
                    title: 'Your garden wishlist is waiting.',
                    message: 'Save plants you love and come back anytime.',
                    actionLabel: 'Discover plants',
                    onAction: () => context.push('/catalog'),
                    icon: Icons.favorite_border_rounded,
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(AppSpace.screen),
                    itemCount: items.length,
                    itemBuilder: (context, i) {
                      final row = items[i];
                      final product = row.product;
                      final busy = _busyIds.contains(product.id);
                      return AppSurfaceCard(
                        margin: const EdgeInsets.only(bottom: AppSpace.md),
                        padding: const EdgeInsets.all(AppSpace.md),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(AppRadii.md),
                              child: product.thumbnailUrl == null
                                  ? Container(
                                      width: 72,
                                      height: 72,
                                      color: AppColors.surfaceMuted,
                                    )
                                  : CachedNetworkImage(
                                      imageUrl: product.thumbnailUrl!,
                                      width: 72,
                                      height: 72,
                                      fit: BoxFit.cover,
                                      memCacheWidth: 216,
                                    ),
                            ),
                            const SizedBox(width: AppSpace.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  GestureDetector(
                                    onTap: () => context.push(
                                      '/product/${product.slug}',
                                    ),
                                    child: Text(
                                      product.name,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleSmall
                                          ?.copyWith(
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                  ),
                                  const SizedBox(height: AppSpace.xs),
                                  Text(
                                    money(product.price),
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.primaryDeep,
                                        ),
                                  ),
                                  const SizedBox(height: AppSpace.sm),
                                  Wrap(
                                    spacing: AppSpace.sm,
                                    children: [
                                      FilledButton(
                                        onPressed: busy || product.isOutOfStock
                                            ? null
                                            : () => _moveToCart(row),
                                        child: Text(
                                          busy
                                              ? '…'
                                              : product.isOutOfStock
                                                  ? 'Out of stock'
                                                  : 'Move to cart',
                                        ),
                                      ),
                                      TextButton(
                                        onPressed: busy
                                            ? null
                                            : () => _remove(row),
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
                    },
                  ),
                );
              },
            ),
    );
  }
}
