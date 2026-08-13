import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:provider/provider.dart';

const _filters = [
  (id: '', label: 'All'),
  (id: 'active', label: 'Active'),
  (id: 'SHIPPED', label: 'Shipped'),
  (id: 'DELIVERED', label: 'Delivered'),
  (id: 'CANCELLED', label: 'Cancelled'),
];

/// Aligned with order detail + Customer Web (`Order placed` for PENDING_PAYMENT).
const _statusLabels = {
  'PENDING_PAYMENT': 'Order placed',
  'PAYMENT_FAILED': 'Payment failed',
  'CONFIRMED': 'Confirmed',
  'PROCESSING': 'Processing',
  'PACKED': 'Packed',
  'SHIPPED': 'Shipped',
  'OUT_FOR_DELIVERY': 'Out for delivery',
  'DELIVERED': 'Delivered',
  'CANCELLED': 'Cancelled',
  'RETURN_REQUESTED': 'Return requested',
  'RETURNED': 'Returned',
  'REFUNDED': 'Refunded',
  'DELIVERY_FAILED': 'Delivery failed',
};

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  Future<List<OrderSummary>>? _future;
  Object? _lastUserKey;
  String _filter = '';
  bool _busyId = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final user = context.watch<AuthProvider>().user;
    final key = user?.id;
    if (key != _lastUserKey) {
      _lastUserKey = key;
      if (user != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _reload();
        });
      } else if (_future != null) {
        setState(() => _future = null);
      }
    }
  }

  void _reload() {
    final user = context.read<AuthProvider>().user;
    if (user == null) {
      setState(() => _future = null);
      return;
    }
    final query = <String, String>{'per_page': '20'};
    if (_filter.isNotEmpty) query['status'] = _filter;
    setState(() {
      _future = context.read<CatalogRepository>().getOrders().then((r) {
        var list = r.data;
        if (_filter.isNotEmpty) {
          list = list
              .where((o) => o.status.toUpperCase() == _filter.toUpperCase())
              .toList();
        }
        return list;
      });
    });
  }

  AppBadgeTone _tone(String status) {
    if (status == 'DELIVERED') return AppBadgeTone.success;
    if (status == 'CANCELLED' || status == 'PAYMENT_FAILED') {
      return AppBadgeTone.error;
    }
    if (status == 'PENDING_PAYMENT') return AppBadgeTone.warning;
    return AppBadgeTone.brand;
  }

  Future<void> _reorder(OrderSummary order) async {
    if (_busyId) return;
    setState(() => _busyId = true);
    try {
      final data = await context.read<ApiClient>().sendData(
        'POST',
        '/orders/${order.id}/reorder',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      if (!mounted) return;
      final cartJson = data['cart'];
      if (cartJson is Map) {
        context.read<CartProvider>().replaceCart(
          Cart.fromJson(Map<String, dynamic>.from(cartJson)),
        );
      }
      final summary = data['reorder_summary'] is Map
          ? Map<String, dynamic>.from(data['reorder_summary'] as Map)
          : <String, dynamic>{};
      final added = (summary['added'] as List?)?.length ?? 0;
      final unavailable = (summary['unavailable'] as List?)?.length ?? 0;
      final priceChanged = (summary['price_changed'] as List?)?.length ?? 0;
      AppFeedback.success(
        context,
        'Reorder: $added added'
        '${unavailable > 0 ? ', $unavailable unavailable' : ''}'
        '${priceChanged > 0 ? ', $priceChanged price changed' : ''}',
      );
      if (added > 0) {
        await showModalBottomSheet<void>(
          context: context,
          builder: (ctx) => Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Reorder summary',
                  style: Theme.of(ctx).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Text('✓ $added item${added == 1 ? '' : 's'} added to cart'),
                if (unavailable > 0)
                  Text('⚠ $unavailable unavailable'),
                if (priceChanged > 0)
                  Text('⚠ $priceChanged price changed'),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    context.push('/cart');
                  },
                  child: const Text('View cart'),
                ),
              ],
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        e is ApiException ? e.message : 'Reorder failed',
      );
    } finally {
      if (mounted) setState(() => _busyId = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;

    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: user == null
          ? EmptyStateView(
              title: 'Sign in to see orders',
              message: 'Track deliveries and reorder favourites.',
              actionLabel: 'Sign in',
              onAction: () =>
                  AuthNavigation.pushLogin(context, redirect: '/orders'),
            )
          : Column(
              children: [
                SizedBox(
                  height: 48,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpace.screen,
                      vertical: AppSpace.sm,
                    ),
                    itemCount: _filters.length,
                    separatorBuilder: (_, _) =>
                        const SizedBox(width: AppSpace.sm),
                    itemBuilder: (_, i) {
                      final f = _filters[i];
                      final selected = _filter == f.id;
                      return FilterChip(
                        label: Text(f.label),
                        selected: selected,
                        onSelected: (_) {
                          setState(() => _filter = f.id);
                          _reload();
                        },
                      );
                    },
                  ),
                ),
                Expanded(
                  child: FutureBuilder<List<OrderSummary>>(
                    future: _future,
                    builder: (context, snap) {
                      if (_future == null ||
                          snap.connectionState != ConnectionState.done) {
                        return const OrdersSkeleton();
                      }
                      if (snap.hasError) {
                        return ErrorStateView(
                          title: 'Unable to load orders',
                          message: ErrorStateView.sanitize(
                            snap.error?.toString(),
                          ),
                          onRetry: _reload,
                        );
                      }
                      final orders = snap.data ?? [];
                      if (orders.isEmpty) {
                        return EmptyStateView(
                          title: 'No orders yet',
                          message: 'Find the perfect plant for your space.',
                          actionLabel: 'Start shopping',
                          onAction: () => context.push('/catalog'),
                          icon: Icons.local_florist_outlined,
                        );
                      }
                      return RefreshIndicator(
                        onRefresh: () async => _reload(),
                        child: ListView.separated(
                          padding: const EdgeInsets.all(AppSpace.screen),
                          itemCount: orders.length,
                          separatorBuilder: (_, _) =>
                              const SizedBox(height: AppSpace.sm),
                          itemBuilder: (_, i) {
                            final o = orders[i];
                            return AppSurfaceCard(
                              onTap: () => context.push('/orders/${o.id}'),
                              padding: const EdgeInsets.all(AppSpace.md),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          o.orderNumber,
                                          style: Theme.of(context)
                                              .textTheme
                                              .titleSmall
                                              ?.copyWith(
                                                fontWeight: FontWeight.w800,
                                              ),
                                        ),
                                      ),
                                      AppBadge(
                                        label:
                                            _statusLabels[o.status] ??
                                            o.status.replaceAll('_', ' '),
                                        tone: _tone(o.status),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: AppSpace.xs),
                                  Text(
                                    formatOrderDate(o.placedAt ?? o.createdAt),
                                    style: Theme.of(
                                      context,
                                    ).textTheme.bodySmall,
                                  ),
                                  const SizedBox(height: AppSpace.md),
                                  Row(
                                    children: [
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(
                                          AppRadii.md,
                                        ),
                                        child: o.thumbnail == null
                                            ? Container(
                                                width: 56,
                                                height: 56,
                                                color: AppColors.surfaceMuted,
                                                alignment: Alignment.center,
                                                child: const Icon(
                                                  Icons.local_florist_outlined,
                                                  color: AppColors.muted,
                                                ),
                                              )
                                            : CachedNetworkImage(
                                                imageUrl: o.thumbnail!,
                                                width: 56,
                                                height: 56,
                                                fit: BoxFit.cover,
                                                memCacheWidth: 168,
                                                errorWidget:
                                                    (context, url, error) =>
                                                        Container(
                                                          width: 56,
                                                          height: 56,
                                                          color: AppColors
                                                              .surfaceMuted,
                                                        ),
                                              ),
                                      ),
                                      const SizedBox(width: AppSpace.md),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              o.previewName ??
                                                  '${o.itemCount ?? 0} item${(o.itemCount ?? 0) == 1 ? '' : 's'}',
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                            if (o.itemCount != null)
                                              Text(
                                                '${o.itemCount} item${o.itemCount == 1 ? '' : 's'}',
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.bodySmall,
                                              ),
                                            Text(
                                              money(o.grandTotal, o.currency),
                                              style: const TextStyle(
                                                fontWeight: FontWeight.w800,
                                                color: AppColors.primaryDeep,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: AppSpace.md),
                                  Wrap(
                                    spacing: AppSpace.sm,
                                    runSpacing: AppSpace.sm,
                                    children: [
                                      OutlinedButton(
                                        onPressed: () =>
                                            context.push('/orders/${o.id}'),
                                        child: const Text('View'),
                                      ),
                                      if (const {
                                        'SHIPPED',
                                        'OUT_FOR_DELIVERY',
                                        'PACKED',
                                        'PROCESSING',
                                        'CONFIRMED',
                                      }.contains(o.status))
                                        OutlinedButton(
                                          onPressed: () =>
                                              context.push('/orders/${o.id}'),
                                          child: const Text('Track'),
                                        ),
                                      if (o.canReorder)
                                        FilledButton.tonal(
                                          onPressed: _busyId
                                              ? null
                                              : () => _reorder(o),
                                          child: const Text('Reorder'),
                                        ),
                                    ],
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
    );
  }
}
