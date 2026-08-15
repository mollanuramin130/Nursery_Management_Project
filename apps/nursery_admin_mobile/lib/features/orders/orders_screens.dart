import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/core/order_transitions.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';

class OrdersListScreen extends StatefulWidget {
  const OrdersListScreen({super.key});

  @override
  State<OrdersListScreen> createState() => _OrdersListScreenState();
}

class _OrdersListScreenState extends State<OrdersListScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrdersProvider>().load(reset: true);
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final orders = context.watch<OrdersProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Orders')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: Column(
              children: [
                TextField(
                  controller: _search,
                  decoration: const InputDecoration(
                    hintText: 'Order #, customer, email',
                    prefixIcon: Icon(Icons.search),
                  ),
                  onChanged: (v) {
                    _debounce?.cancel();
                    _debounce = Timer(const Duration(milliseconds: 400), () {
                      orders.q = v;
                      orders.load(reset: true);
                    });
                  },
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: orders.status.isEmpty ? '' : orders.status,
                  decoration: const InputDecoration(labelText: 'Status'),
                  items: const [
                    DropdownMenuItem(value: '', child: Text('All')),
                    DropdownMenuItem(value: 'PENDING_PAYMENT', child: Text('Pending payment')),
                    DropdownMenuItem(value: 'PAYMENT_FAILED', child: Text('Payment failed')),
                    DropdownMenuItem(value: 'CONFIRMED', child: Text('Confirmed')),
                    DropdownMenuItem(value: 'PROCESSING', child: Text('Processing')),
                    DropdownMenuItem(value: 'PACKED', child: Text('Packed')),
                    DropdownMenuItem(value: 'SHIPPED', child: Text('Shipped')),
                    DropdownMenuItem(value: 'OUT_FOR_DELIVERY', child: Text('Out for delivery')),
                    DropdownMenuItem(value: 'DELIVERY_FAILED', child: Text('Delivery failed')),
                    DropdownMenuItem(value: 'DELIVERED', child: Text('Delivered')),
                    DropdownMenuItem(value: 'RETURN_REQUESTED', child: Text('Return requested')),
                    DropdownMenuItem(value: 'RETURNED', child: Text('Returned')),
                    DropdownMenuItem(value: 'REFUNDED', child: Text('Refunded')),
                    DropdownMenuItem(value: 'CANCELLED', child: Text('Cancelled')),
                  ],
                  onChanged: (v) {
                    orders.status = v ?? '';
                    orders.load(reset: true);
                  },
                ),
              ],
            ),
          ),
          Expanded(
            child: orders.loading && orders.orders.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : orders.error != null && orders.orders.isEmpty
                    ? OpsError(
                        message: orders.error!,
                        onRetry: () => orders.load(reset: true),
                      )
                    : orders.orders.isEmpty
                        ? const OpsEmpty(
                            title: 'No orders',
                            subtitle: 'No orders match your filters.',
                          )
                        : Column(
                            children: [
                              if (orders.error != null)
                                OpsStaleBanner(
                                  message: orders.error!,
                                  onRetry: () => orders.load(reset: true),
                                ),
                              Expanded(
                                child: RefreshIndicator(
                                  onRefresh: () => orders.load(reset: true),
                                  child: ListView.builder(
                                    itemCount: orders.orders.length,
                                    itemBuilder: (context, i) {
                                      final o = orders.orders[i];
                                      return Card(
                                        margin: const EdgeInsets.symmetric(
                                          horizontal: 12,
                                          vertical: 6,
                                        ),
                                        child: ListTile(
                                          title: Text(o.orderNumber),
                                          subtitle: Text(
                                            '${o.customerName ?? '—'}\n${money(o.grandTotal)} · ${opsPaymentStatusLabel(o.paymentStatus ?? '')}',
                                          ),
                                          isThreeLine: true,
                                          trailing: OpsStatusChip(o.status),
                                          onTap: () =>
                                              context.push('/orders/${o.id}'),
                                        ),
                                      );
                                    },
                                  ),
                                ),
                              ),
                            ],
                          ),
          ),
          if (orders.lastPage > 1)
            Padding(
              padding: const EdgeInsets.all(8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  TextButton(
                    onPressed: orders.page <= 1
                        ? null
                        : () {
                            orders.page -= 1;
                            orders.load();
                          },
                    child: const Text('Previous'),
                  ),
                  Text('${orders.page} / ${orders.lastPage}'),
                  TextButton(
                    onPressed: orders.page >= orders.lastPage
                        ? null
                        : () {
                            orders.page += 1;
                            orders.load();
                          },
                    child: const Text('Next'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrdersProvider>().loadDetail(widget.orderId);
    });
  }

  bool _statusBusy = false;

  Future<void> _updateStatus(String next) async {
    if (_statusBusy) return;
    final ok = await confirmAction(
      context,
      title: 'Update order status?',
      body:
          'Set status to ${opsStatusLabel(next)}. Backend validates the transition.',
      confirmLabel: 'Update',
      danger: next == 'CANCELLED',
    );
    if (!ok || !mounted) return;
    setState(() => _statusBusy = true);
    final err = await context.read<OrdersProvider>().updateStatus(
          widget.orderId,
          next,
        );
    if (!mounted) return;
    setState(() => _statusBusy = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(err ?? 'Status updated')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final orders = context.watch<OrdersProvider>();
    final auth = context.watch<AuthProvider>();
    final detail = orders.detail;

    return Scaffold(
      appBar: AppBar(
        title: Text(detail?['order_number']?.toString() ?? 'Order'),
      ),
      body: orders.loading && detail == null
          ? const Center(child: CircularProgressIndicator())
          : orders.error != null && detail == null
              ? OpsError(
                  message: orders.error!,
                  onRetry: () => orders.loadDetail(widget.orderId),
                )
              : detail == null
                  ? const OpsEmpty(title: 'Order not found')
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        if (orders.error != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: OpsStaleBanner(
                              message: orders.error!,
                              onRetry: () =>
                                  orders.loadDetail(widget.orderId),
                            ),
                          ),
                        Row(
                          children: [
                            OpsStatusChip(detail['status']?.toString() ?? ''),
                            const Spacer(),
                            Text(
                              money(detail['grand_total'] as num?),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 18,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'Customer: ${(detail['customer'] is Map) ? detail['customer']['name'] : '—'}',
                        ),
                        if (detail['customer'] is Map &&
                            detail['customer']['email'] != null)
                          Text('Email: ${detail['customer']['email']}'),
                        Builder(
                          builder: (_) {
                            final pay = detail['payment'] is Map
                                ? Map<String, dynamic>.from(
                                    detail['payment'] as Map,
                                  )
                                : null;
                            final method = (pay?['method'] ??
                                    detail['payment_method'] ??
                                    '—')
                                .toString()
                                .toUpperCase();
                            final status = (pay?['status'] ??
                                    detail['payment_status'] ??
                                    '—')
                                .toString();
                            final mode = pay?['upi_mode']?.toString();
                            final txn =
                                pay?['provider_payment_id']?.toString();
                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Payment: $method · ${opsPaymentStatusLabel(status)}${mode != null && mode.isNotEmpty ? ' · $mode' : ''}',
                                ),
                                if (txn != null && txn.isNotEmpty)
                                  Text(
                                    'Txn: $txn',
                                    style: const TextStyle(fontSize: 12),
                                  ),
                              ],
                            );
                          },
                        ),
                        const Divider(height: 28),
                        const Text(
                          'Items',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        ...(detail['items'] as List? ?? []).map((raw) {
                          final item = Map<String, dynamic>.from(raw as Map);
                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              item['product_name']?.toString() ?? 'Item',
                            ),
                            subtitle: Text('Qty ${item['quantity']}'),
                            trailing: Text(money(item['line_total'] as num?)),
                          );
                        }),
                        if (auth.can('orders.update_status')) ...[
                          const Divider(height: 28),
                          const Text(
                            'Update status',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: allowedOrderTransitions(
                              detail['status']?.toString() ?? '',
                            )
                                .map(
                                  (s) => OutlinedButton(
                                    onPressed: _statusBusy
                                        ? null
                                        : () => _updateStatus(s),
                                    child: Text(opsStatusLabel(s)),
                                  ),
                                )
                                .toList(),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Only allowed next statuses are shown. Backend still validates.',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ],
                    ),
    );
  }
}
