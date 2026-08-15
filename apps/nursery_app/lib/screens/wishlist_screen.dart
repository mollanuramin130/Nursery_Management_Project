import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class WishlistScreen extends StatefulWidget {
  const WishlistScreen({super.key});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  List<WishlistRow>? _items;
  String? _error;
  bool _loading = false;
  final Set<int> _busyIds = {};
  int _listEpoch = 0;
  Set<int> _lastSyncedIds = {};
  int _lastSyncGen = -1;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload(initial: true));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // QA-40: IndexedStack keep-alive can leave a stale row list after hearts
    // change on Home/Shop/PDP — soft-sync when provider ids diverge.
    final ids = context.watch<WishlistProvider>().ids;
    final next = Set<int>.from(ids);
    if (_items != null &&
        !_setEquals(next, _lastSyncedIds) &&
        _busyIds.isEmpty) {
      _lastSyncedIds = next;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _busyIds.isEmpty) _reload();
      });
    } else if (_items == null) {
      _lastSyncedIds = next;
    }

    final gen = context.watch<OfflineController>().syncGeneration;
    if (_lastSyncGen >= 0 &&
        gen != _lastSyncGen &&
        _items != null &&
        _busyIds.isEmpty) {
      _lastSyncGen = gen;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _busyIds.isEmpty) _reload();
      });
    } else {
      _lastSyncGen = gen;
    }
  }

  bool _setEquals(Set<int> a, Set<int> b) {
    if (identical(a, b)) return true;
    if (a.length != b.length) return false;
    return a.containsAll(b);
  }

  /// QA-36-008 / QA-38: never flash skeleton or restore a removed row mid-flight.
  Future<void> _reload({bool initial = false}) async {
    final auth = context.read<AuthProvider>();
    final wish = context.read<WishlistProvider>();
    if (auth.user == null && wish.ids.isEmpty) {
      setState(() {
        _items = null;
        _error = null;
        _loading = false;
      });
      return;
    }

    // Never skeleton over an already-visible list (flicker root cause).
    final showSkeleton = initial && _items == null;
    if (showSkeleton) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    final epoch = ++_listEpoch;
    try {
      final repo = context.read<CatalogRepository>();
      final resolved = await repo.wishlistProducts();
      if (!mounted || epoch != _listEpoch) return;

      final wishNow = context.read<WishlistProvider>();
      // Authoritative heart set is the provider (optimistic removes win).
      final idSet = Set<int>.from(wishNow.ids);
      final byId = {for (final p in resolved.data) p.id: p};
      final missing = <ProductSummary>[];
      for (final id in idSet) {
        if (!byId.containsKey(id)) {
          final p = await repo.productById(id);
          if (p != null) missing.add(p);
        }
      }
      if (!mounted || epoch != _listEpoch) return;

      final products = [
        ...idSet.map((id) => byId[id]).whereType<ProductSummary>(),
        ...missing,
      ];
      final rows =
          products.map((p) => WishlistRow(product: p)).toList();

      // Do not replace list while a remove/move is in flight.
      if (_busyIds.isNotEmpty) return;

      setState(() {
        _items = rows;
        _error = null;
        _loading = false;
        _lastSyncedIds = idSet;
      });
      // QA-39: do NOT re-bootstrap here — bootstrap(loading:true) + API race
      // was restoring removed IDs and flashing the old list for a frame.
    } catch (e) {
      if (!mounted || epoch != _listEpoch) return;
      setState(() {
        _loading = false;
        if (_items == null) {
          _error = e is ApiException ? e.message : 'Unable to load wishlist';
        }
      });
    }
  }

  Future<void> _moveToCart(WishlistRow row) async {
    final productId = row.product.id;
    if (_busyIds.contains(productId)) return;

    // Optimistic remove — bump epoch so in-flight reloads cannot restore the row.
    final previous = List<WishlistRow>.from(_items ?? const []);
    _listEpoch++;
    setState(() {
      _busyIds.add(productId);
      _items = previous.where((r) => r.product.id != productId).toList();
    });

    try {
      final cart = await context.read<WishlistProvider>().moveToCart(productId);
      if (!mounted) return;
      context.read<CartProvider>().replaceCart(cart);
      AppFeedback.success(context, 'Moved to cart');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _items = previous);
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _items = previous);
      AppFeedback.error(context, 'Unable to move to cart.');
    } finally {
      if (mounted) setState(() => _busyIds.remove(productId));
    }
  }

  Future<void> _remove(WishlistRow row) async {
    final productId = row.product.id;
    if (_busyIds.contains(productId)) return;

    // Optimistic remove — bump epoch so in-flight reloads cannot restore the row.
    final previous = List<WishlistRow>.from(_items ?? const []);
    _listEpoch++;
    setState(() {
      _busyIds.add(productId);
      _items = previous.where((r) => r.product.id != productId).toList();
    });

    try {
      await context.read<WishlistProvider>().remove(productId);
      if (!mounted) return;
      AppFeedback.success(context, 'Removed from wishlist');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _items = previous);
      AppFeedback.error(context, e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _items = previous);
      AppFeedback.error(context, 'Unable to remove item.');
    } finally {
      if (mounted) setState(() => _busyIds.remove(productId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final localHearts = context.watch<WishlistProvider>().ids.isNotEmpty;
    final showSignInGate = user == null && !localHearts && _items == null;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Wishlist'),
        actions: [
          if (context.watch<WishlistProvider>().localOnly)
            const Padding(
              padding: EdgeInsets.only(right: 12),
              child: Center(
                child: Text(
                  'Saved on this device',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ),
            ),
        ],
      ),
      body: showSignInGate
          ? EmptyStateView(
              title: 'Sign in to save plants',
              message: 'Your wishlist stays with your account.',
              actionLabel: 'Sign in',
              onAction: () =>
                  AuthNavigation.pushLogin(context, redirect: '/wishlist'),
              icon: Icons.favorite_border_rounded,
            )
          : _loading && _items == null
              ? const ProductGridSkeleton(count: 4)
              : _error != null && _items == null
                  ? ErrorStateView(
                      title: 'Unable to load wishlist',
                      message: ErrorStateView.sanitize(_error),
                      onRetry: () => _reload(initial: true),
                    )
                  : (_items == null || _items!.isEmpty)
                      ? EmptyStateView(
                          title: 'Your garden wishlist is waiting.',
                          message:
                              'Save plants you love and come back anytime.',
                          actionLabel: 'Discover plants',
                          onAction: () => context.push('/catalog'),
                          icon: Icons.favorite_border_rounded,
                        )
                      : RefreshIndicator(
                          onRefresh: () => _reload(),
                          child: ListView.builder(
                            padding: const EdgeInsets.all(AppSpace.screen),
                            itemCount: _items!.length,
                            itemBuilder: (context, i) {
                              final row = _items![i];
                              final product = row.product;
                              final busy = _busyIds.contains(product.id);
                              return AppSurfaceCard(
                                margin:
                                    const EdgeInsets.only(bottom: AppSpace.md),
                                padding: const EdgeInsets.all(AppSpace.md),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    ClipRRect(
                                      borderRadius:
                                          BorderRadius.circular(AppRadii.md),
                                      child: ResilientNetworkImage(
                                        url: product.thumbnailUrl,
                                        width: 72,
                                        height: 72,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                    const SizedBox(width: AppSpace.md),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
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
                                                onPressed: busy ||
                                                        product.isOutOfStock
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
                        ),
    );
  }
}
