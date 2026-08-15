import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/services/razorpay_checkout.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

const _flow = [
  'PENDING_PAYMENT',
  'CONFIRMED',
  'PROCESSING',
  'PACKED',
  'SHIPPED',
  'OUT_FOR_DELIVERY',
  'DELIVERED',
];

const _labels = {
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

const _cancelReasons = [
  (code: 'changed_mind', label: 'Changed my mind'),
  (code: 'ordered_by_mistake', label: 'Ordered by mistake'),
  (code: 'better_price', label: 'Found a better price'),
  (code: 'delivery_slow', label: 'Delivery is taking too long'),
  (code: 'no_longer_needed', label: 'Product no longer needed'),
  (code: 'other', label: 'Other'),
];

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({
    super.key,
    required this.orderId,
    this.placedNumber,
    this.paymentPending = false,
    this.paid = false,
  });

  final int orderId;
  final String? placedNumber;
  final bool paymentPending;
  final bool paid;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late Future<OrderDetail> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<OrderDetail> _load() {
    return context.read<ApiClient>().getData(
      '/orders/${widget.orderId}',
      map: (data) =>
          OrderDetail.fromJson(Map<String, dynamic>.from(data as Map)),
    );
  }

  Future<void> _retryPayment(OrderDetail order) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final api = context.read<ApiClient>();
      final pay = await api.sendData(
        'POST',
        '/orders/${order.id}/retry-payment',
        map: (data) => Map<String, dynamic>.from(data as Map),
      );
      final clientPayload = Map<String, dynamic>.from(
        (pay['client_payload'] as Map?) ?? {},
      );
      final gateway = await openRazorpayCheckout(
        clientPayload: {
          ...clientPayload,
          'order_id':
              pay['provider_order_id']?.toString() ?? clientPayload['order_id'],
        },
        description: 'Order ${order.orderNumber}',
      );
      final verified = await api.sendData(
        'POST',
        '/payments/verify',
        body: {
          'payment_id': pay['payment_id'],
          'provider_order_id': gateway.orderId,
          'provider_payment_id': gateway.paymentId,
          'provider_signature': gateway.signature,
        },
        map: (data) => Map<String, dynamic>.from(data as Map),
      );
      if (!mounted) return;
      if (verified['payment_status']?.toString() != 'success') {
        AppFeedback.error(context, 'Payment could not be confirmed');
      } else {
        AppFeedback.success(context, 'Payment successful');
      }
      await softReplaceFuture(
        load: _load,
        onData: (data) {
          if (!mounted) return;
          setState(() => _future = completedFuture(data));
        },
      );
    } on RazorpayCheckoutCancelled {
      if (!mounted) return;
      AppFeedback.info(context, 'Payment cancelled');
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        e is ApiException
            ? e.message
            : (e is RazorpayCheckoutFailed
                  ? e.message
                  : 'Unable to retry payment'),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showCancelSheet(OrderDetail order) async {
    var reasonCode = 'changed_mind';
    var otherText = '';
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            return Padding(
              padding: EdgeInsets.fromLTRB(
                20,
                20,
                20,
                20 + MediaQuery.of(ctx).viewInsets.bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Cancel order?',
                    style: Theme.of(ctx).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    order.orderNumber,
                    style: Theme.of(ctx).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 12),
                  ..._cancelReasons.map(
                    (r) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        reasonCode == r.code
                            ? Icons.radio_button_checked
                            : Icons.radio_button_off,
                        color: AppColors.primary,
                      ),
                      title: Text(r.label),
                      onTap: () => setModal(() => reasonCode = r.code),
                    ),
                  ),
                  if (reasonCode == 'other')
                    TextField(
                      decoration: const InputDecoration(
                        hintText: 'Tell us more',
                      ),
                      onChanged: (v) => otherText = v,
                    ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Navigator.pop(ctx, false),
                          child: const Text('Keep order'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: AppColors.error,
                          ),
                          onPressed: () => Navigator.pop(ctx, true),
                          child: const Text('Cancel order'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );
    if (confirmed != true || !mounted) return;
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await context.read<ApiClient>().sendData(
        'POST',
        '/orders/${order.id}/cancel',
        body: {
          'reason_code': reasonCode,
          if (reasonCode == 'other' && otherText.trim().isNotEmpty)
            'reason': otherText.trim(),
        },
        map: (data) => data,
      );
      if (!mounted) return;
      AppFeedback.success(context, 'Order cancelled');
      await softReplaceFuture(
        load: _load,
        onData: (data) {
          if (!mounted) return;
          setState(() => _future = completedFuture(data));
        },
      );
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        e is ApiException ? e.message : 'Could not cancel order',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showReturnSheet(OrderDetail order) async {
    final returnable = order.returnableItems.isNotEmpty
        ? order.items
            .where((i) => order.returnableQtyFor(i.id) > 0)
            .toList()
        : order.items;
    if (returnable.isEmpty) return;
    var itemId = returnable.first.id;
    var qty = 1;
    final reasons = order.returnReasons.isNotEmpty
        ? order.returnReasons
        : [
            {'code': 'damaged', 'label': 'Damaged or unhealthy plant'},
            {'code': 'wrong_item', 'label': 'Wrong item received'},
            {'code': 'not_as_described', 'label': 'Not as described'},
            {'code': 'changed_mind', 'label': 'Changed my mind'},
            {'code': 'other', 'label': 'Other'},
          ];
    var reasonCode = reasons.first['code']?.toString() ?? 'damaged';
    final notesCtrl = TextEditingController();

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            final item = returnable.firstWhere(
              (i) => i.id == itemId,
              orElse: () => returnable.first,
            );
            final maxQty = order.returnableItems.isNotEmpty
                ? order.returnableQtyFor(item.id)
                : item.quantity;
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 28,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Return item',
                      style: Theme.of(ctx).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      value: itemId,
                      decoration: const InputDecoration(labelText: 'Item'),
                      items: returnable
                          .map(
                            (i) => DropdownMenuItem(
                              value: i.id,
                              child: Text(
                                '${i.name} (returnable ${order.returnableItems.isNotEmpty ? order.returnableQtyFor(i.id) : i.quantity})',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (v) {
                        if (v == null) return;
                        setModal(() {
                          itemId = v;
                          qty = 1;
                        });
                      },
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Text('Qty'),
                        const Spacer(),
                        IconButton(
                          onPressed: qty > 1
                              ? () => setModal(() => qty--)
                              : null,
                          icon: const Icon(Icons.remove_circle_outline),
                        ),
                        Text('$qty'),
                        IconButton(
                          onPressed: qty < maxQty
                              ? () => setModal(() => qty++)
                              : null,
                          icon: const Icon(Icons.add_circle_outline),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ...reasons.map((r) {
                      final code = r['code']?.toString() ?? '';
                      final label = r['label']?.toString() ?? code;
                      return RadioListTile<String>(
                        dense: true,
                        title: Text(label),
                        value: code,
                        groupValue: reasonCode,
                        onChanged: (v) =>
                            setModal(() => reasonCode = v ?? reasonCode),
                      );
                    }),
                    TextField(
                      controller: notesCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Notes (optional)',
                      ),
                      maxLines: 2,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      child: const Text('Submit return'),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    if (confirmed != true || !mounted) {
      notesCtrl.dispose();
      return;
    }

    setState(() => _busy = true);
    try {
      await context.read<ApiClient>().sendData(
        'POST',
        '/orders/${order.id}/returns',
        body: {
          'items': [
            {
              'order_item_id': itemId,
              'quantity': qty,
              'reason': reasonCode,
            },
          ],
          if (notesCtrl.text.trim().isNotEmpty) 'notes': notesCtrl.text.trim(),
        },
        map: (d) => d,
      );
      if (!mounted) return;
      AppFeedback.success(context, 'Your return request has been submitted.');
      await softReplaceFuture(
        load: _load,
        onData: (data) {
          if (!mounted) return;
          setState(() => _future = completedFuture(data));
        },
      );
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        e is ApiException ? e.message : 'Return failed',
      );
    } finally {
      notesCtrl.dispose();
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reorder(OrderDetail order) async {
    if (_busy) return;
    setState(() => _busy = true);
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
              Text('✓ $added item${added == 1 ? '' : 's'} added'),
              if (unavailable > 0) Text('⚠ $unavailable unavailable'),
              if (priceChanged > 0) Text('⚠ $priceChanged price changed'),
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
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        e is ApiException ? e.message : 'Reorder failed',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  int _activeIndex(OrderDetail order) {
    if (order.status == 'CANCELLED') return -1;
    final idx = _flow.indexOf(order.status);
    if (idx >= 0) return idx;
    if (order.status == 'PAYMENT_FAILED') return 0;
    for (var i = _flow.length - 1; i >= 0; i--) {
      if (order.statusHistory.any((h) => h['status'] == _flow[i])) {
        return i;
      }
    }
    return -1;
  }

  AppBadgeTone _tone(String status) {
    if (status == 'DELIVERED') return AppBadgeTone.success;
    if (status == 'CANCELLED' || status == 'PAYMENT_FAILED') {
      return AppBadgeTone.error;
    }
    if (status == 'PENDING_PAYMENT') return AppBadgeTone.warning;
    return AppBadgeTone.brand;
  }

  String _paymentLabel(OrderDetail order) {
    final status = order.payment?['status']?.toString();
    final method =
        order.payment?['method']?.toString() ?? order.paymentMethod ?? '';
    if (status == 'refund_pending') return 'Refund pending';
    if (method == 'cod' || status == 'cod' || status == 'not_required') {
      return 'Not required (COD)';
    }
    if (status == 'success') return 'Paid';
    return status ?? method;
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;

    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Order')),
        body: EmptyStateView(
          title: 'Sign in to track orders',
          message: 'Order tracking is available on your account.',
          actionLabel: 'Sign in',
          onAction: () => AuthNavigation.pushLogin(
            context,
            redirect: '/orders/${widget.orderId}',
          ),
        ),
      );
    }

    return FutureBuilder<OrderDetail>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Scaffold(body: OrdersSkeleton());
        }
        if (snap.hasError || !snap.hasData) {
          return Scaffold(
            appBar: AppBar(title: const Text('Order')),
            body: ErrorStateView(
              title: 'Unable to load order',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () {
                softReplaceFuture(
                  load: _load,
                  onData: (data) {
                    if (!mounted) return;
                    setState(() => _future = completedFuture(data));
                  },
                );
              },
            ),
          );
        }

        final order = snap.data!;
        final active = _activeIndex(order);
        final addr = order.shippingAddress;
        final shipment = order.shipment ??
            (order.tracking?['shipment'] is Map
                ? Map<String, dynamic>.from(order.tracking!['shipment'] as Map)
                : null);
        final timeline = order.timeline;

        return Scaffold(
          appBar: AppBar(
            title: const Text('Order details'),
            actions: [
              IconButton(
                tooltip: 'Refresh',
                onPressed: _busy
                    ? null
                    : () async {
                        await softReplaceFuture(
                          load: _load,
                          onData: (data) {
                            if (!mounted) return;
                            setState(() => _future = completedFuture(data));
                          },
                          onError: (e) {
                            if (!mounted) return;
                            AppFeedback.error(context, e.toString());
                          },
                        );
                      },
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          bottomNavigationBar: null,
          body: RefreshIndicator(
            onRefresh: () async {
              await softReplaceFuture(
                load: _load,
                onData: (data) {
                  if (!mounted) return;
                  setState(() => _future = completedFuture(data));
                },
                onError: (e) {
                  if (!mounted) return;
                  AppFeedback.error(context, e.toString());
                },
              );
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                // QA-36-001: paid=1 query must not invent "Order confirmed".
                if (widget.placedNumber != null ||
                    order.status == 'PENDING_PAYMENT' ||
                    order.status == 'PAYMENT_FAILED' ||
                    order.status == 'CONFIRMED') ...[
                  AppSurfaceCard(
                    padding: const EdgeInsets.all(AppSpace.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          // QA-35-002: customer PENDING_PAYMENT title = "Order placed"
                          order.status == 'PENDING_PAYMENT' ||
                                  widget.paymentPending
                              ? 'Order placed'
                              : order.status == 'PAYMENT_FAILED'
                              ? 'Payment failed'
                              : order.status == 'CONFIRMED'
                              ? 'Order confirmed'
                              : 'Order ${widget.placedNumber ?? order.orderNumber} placed',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color:
                                order.status == 'PENDING_PAYMENT' ||
                                    widget.paymentPending
                                ? AppColors.warning
                                : order.status == 'PAYMENT_FAILED'
                                ? AppColors.error
                                : order.status == 'CONFIRMED'
                                ? AppColors.success
                                : AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: AppSpace.xs),
                        Text(
                          order.status == 'PENDING_PAYMENT' ||
                                  widget.paymentPending
                              ? 'Complete payment to confirm. Your cart remains available if you cancel this unpaid order.'
                              : order.status == 'PAYMENT_FAILED'
                              ? 'Retry payment or return to cart. No charge was confirmed.'
                              : order.status == 'CONFIRMED'
                              ? 'Thank you for your purchase! ${order.orderNumber} · ${money(order.grandTotal)}'
                              : 'Order ${order.orderNumber} · ${money(order.grandTotal)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        if (order.status == 'PENDING_PAYMENT' ||
                            order.status == 'PAYMENT_FAILED') ...[
                          const SizedBox(height: AppSpace.md),
                          FilledButton(
                            onPressed: _busy
                                ? null
                                : () => _retryPayment(order),
                            child: Text(
                              _busy
                                  ? 'Opening…'
                                  : 'Pay ${money(order.grandTotal)}',
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                ],
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            order.orderNumber,
                            style: Theme.of(context).textTheme.headlineMedium,
                          ),
                          Text(
                            'Placed ${formatOrderDate(order.placedAt ?? order.createdAt)}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    AppBadge(
                      label: _labels[order.status] ?? order.status,
                      tone: _tone(order.status),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (order.status == 'CANCELLED') ...[
                  AppSurfaceCard(
                    padding: const EdgeInsets.all(AppSpace.md),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Order cancelled',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.error,
                          ),
                        ),
                        if (order.cancelReason != null) ...[
                          const SizedBox(height: 6),
                          Text('Reason: ${order.cancelReason}'),
                        ],
                        if (order.cancelledAt != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Cancelled ${formatOrderDate(order.cancelledAt)}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                        const SizedBox(height: 8),
                        Text('Payment: ${_paymentLabel(order)}'),
                      ],
                    ),
                  ),
                ] else ...[
                  Text(
                    'Track your order',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  if (timeline.isNotEmpty)
                    ...List.generate(timeline.length, (i) {
                      final step = timeline[i];
                      return IntrinsicHeight(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Column(
                              children: [
                                Container(
                                  width: 16,
                                  height: 16,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: step.completed
                                        ? AppColors.primary
                                        : AppColors.borderStrong,
                                    border: step.current
                                        ? Border.all(
                                            color: AppColors.primaryDeep,
                                            width: 2,
                                          )
                                        : null,
                                  ),
                                  alignment: Alignment.center,
                                  child: step.completed
                                      ? const Icon(
                                          Icons.check,
                                          size: 10,
                                          color: Colors.white,
                                        )
                                      : null,
                                ),
                                if (i < timeline.length - 1)
                                  Expanded(
                                    child: Container(
                                      width: 2,
                                      color: step.completed
                                          ? AppColors.primary
                                          : AppColors.border,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Padding(
                                padding: EdgeInsets.only(
                                  bottom: i < timeline.length - 1 ? 18 : 0,
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      step.title,
                                      style: TextStyle(
                                        fontWeight: step.current
                                            ? FontWeight.w800
                                            : FontWeight.w500,
                                        color: step.completed
                                            ? AppColors.ink
                                            : AppColors.muted,
                                      ),
                                    ),
                                    if (step.createdAt != null)
                                      Text(
                                        formatOrderDate(step.createdAt),
                                        style: Theme.of(
                                          context,
                                        ).textTheme.bodySmall,
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    })
                  else
                    ...List.generate(_flow.length, (i) {
                      final done = active >= i;
                      final current = active == i;
                      return IntrinsicHeight(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Column(
                              children: [
                                Container(
                                  width: 14,
                                  height: 14,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: done
                                        ? AppColors.primary
                                        : AppColors.borderStrong,
                                    border: current
                                        ? Border.all(
                                            color: AppColors.primaryDeep,
                                            width: 2,
                                          )
                                        : null,
                                  ),
                                ),
                                if (i < _flow.length - 1)
                                  Expanded(
                                    child: Container(
                                      width: 2,
                                      color: active > i
                                          ? AppColors.primary
                                          : AppColors.border,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Padding(
                                padding: EdgeInsets.only(
                                  bottom: i < _flow.length - 1 ? 18 : 0,
                                ),
                                child: Text(
                                  _labels[_flow[i]] ?? _flow[i],
                                  style: TextStyle(
                                    fontWeight: current
                                        ? FontWeight.w800
                                        : FontWeight.w500,
                                    color: done ? AppColors.ink : AppColors.muted,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                ],
                if (shipment != null &&
                    (shipment['tracking_number'] != null ||
                        shipment['carrier'] != null)) ...[
                  const SizedBox(height: 20),
                  Text(
                    'Shipment',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  if (shipment['carrier'] != null)
                    Text('Carrier: ${shipment['carrier']}'),
                  if (shipment['tracking_number'] != null)
                    Text('Tracking: ${shipment['tracking_number']}'),
                  if (order.tracking?['estimated_delivery'] != null)
                    Text(
                      'Estimated delivery: ${order.tracking!['estimated_delivery']}',
                    ),
                  if (order.tracking?['events'] is List &&
                      (order.tracking!['events'] as List).isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Recent updates',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    const SizedBox(height: 4),
                    ...(order.tracking!['events'] as List)
                        .reversed
                        .take(5)
                        .map((raw) {
                      final ev = Map<String, dynamic>.from(raw as Map);
                      final when = ev['event_at']?.toString() ?? '';
                      final status = ev['status']?.toString() ?? '';
                      final desc = ev['description']?.toString();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '$when · $status${desc != null && desc.isNotEmpty ? ' — $desc' : ''}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      );
                    }),
                  ],
                ],
                const SizedBox(height: 24),
                Text('Items', style: Theme.of(context).textTheme.headlineMedium),
                const SizedBox(height: 12),
                ...order.items.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
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
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.name,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text(
                                'Qty ${item.quantity}',
                                style: const TextStyle(color: AppColors.muted),
                              ),
                              if (item.productId != null &&
                                  order.reviewableProductIds.contains(
                                    item.productId,
                                  ) &&
                                  (item.productSlug?.isNotEmpty ?? false))
                                TextButton(
                                  onPressed: () => context.push(
                                    '/product/${item.productSlug}?review=1',
                                  ),
                                  style: TextButton.styleFrom(
                                    padding: EdgeInsets.zero,
                                    minimumSize: Size.zero,
                                    tapTargetSize:
                                        MaterialTapTargetSize.shrinkWrap,
                                  ),
                                  child: const Text('Write a review'),
                                ),
                            ],
                          ),
                        ),
                        Text(
                          money(item.lineTotal),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                ),
                if (addr != null) ...[
                  const SizedBox(height: 20),
                  Text(
                    'Delivery address',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  Text('${addr['name'] ?? ''}'),
                  Text('${addr['line1'] ?? ''}'),
                  if (addr['line2'] != null) Text('${addr['line2']}'),
                  Text(
                    '${addr['city'] ?? ''}, ${addr['state'] ?? ''} ${addr['postal_code'] ?? ''}',
                  ),
                ],
                const SizedBox(height: 20),
                Text(
                  'Payment',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                Text(_paymentLabel(order)),
                if (order.refunds.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'Refunds',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  ...order.refunds.map(
                    (r) => Text(
                      '${money((r['amount'] as num?)?.toDouble() ?? 0)} · ${r['status'] ?? ''}',
                    ),
                  ),
                ],
                if (order.returns.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'Returns',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  ...order.returns.map(
                    (r) {
                      final id = (r['id'] as num?)?.toInt();
                      final status = r['status']?.toString() ?? '';
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('Return #${id ?? ''}'),
                        subtitle: Text(status.replaceAll('_', ' ')),
                        trailing: id == null
                            ? null
                            : const Icon(Icons.chevron_right),
                        onTap: id == null
                            ? null
                            : () => context.push('/account/returns/$id'),
                      );
                    },
                  ),
                ],
                const SizedBox(height: 20),
                Text('Summary', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                _row('Subtotal', money(order.subtotal)),
                if (order.discountTotal > 0)
                  _row('Discount', '-${money(order.discountTotal)}'),
                _row(
                  'Delivery',
                  order.shippingTotal <= 0 ? 'Free' : money(order.shippingTotal),
                ),
                if (order.taxTotal > 0) _row('Tax', money(order.taxTotal)),
                const Divider(height: 24),
                _row('Total', money(order.grandTotal), bold: true),
                // QA-39: in-body actions — avoid stacking a second bottom bar
                // above shell Home/Shop/Cart/Orders/Account tabs.
                if (order.canReturn || order.canReorder || order.canCancel) ...[
                  const SizedBox(height: 24),
                  if (order.canReturn)
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.tonal(
                        onPressed:
                            _busy ? null : () => _showReturnSheet(order),
                        child: const Text('Return item'),
                      ),
                    ),
                  if (order.canReturn &&
                      (order.canReorder || order.canCancel))
                    const SizedBox(height: 8),
                  if (order.canReorder || order.canCancel)
                    Row(
                      children: [
                        if (order.canReorder)
                          Expanded(
                            child: FilledButton(
                              onPressed:
                                  _busy ? null : () => _reorder(order),
                              child: const Text('Reorder'),
                            ),
                          ),
                        if (order.canReorder && order.canCancel)
                          const SizedBox(width: 8),
                        if (order.canCancel)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: _busy
                                  ? null
                                  : () => _showCancelSheet(order),
                              child: const Text('Cancel order'),
                            ),
                          ),
                      ],
                    ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
              color: bold ? AppColors.ink : AppColors.muted,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
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
