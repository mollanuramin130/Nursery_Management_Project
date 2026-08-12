import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
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
                    DropdownMenuItem(value: 'PENDING_PAYMENT', child: Text('Pending pay')),
                    DropdownMenuItem(value: 'CONFIRMED', child: Text('Confirmed')),
                    DropdownMenuItem(value: 'PROCESSING', child: Text('Processing')),
                    DropdownMenuItem(value: 'PACKED', child: Text('Packed')),
                    DropdownMenuItem(value: 'SHIPPED', child: Text('Shipped')),
                    DropdownMenuItem(value: 'OUT_FOR_DELIVERY', child: Text('Out for delivery')),
                    DropdownMenuItem(value: 'DELIVERED', child: Text('Delivered')),
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
                : orders.error != null
                    ? OpsError(
                        message: orders.error!,
                        onRetry: () => orders.load(reset: true),
                      )
                    : orders.orders.isEmpty
                        ? const OpsEmpty(
                            title: 'No orders',
                            subtitle: 'No orders match your filters.',
                          )
                        : RefreshIndicator(
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
                                      '${o.customerName ?? '—'}\n${money(o.grandTotal)} · ${o.paymentStatus ?? '—'}',
                                    ),
                                    isThreeLine: true,
                                    trailing: OpsStatusChip(o.status),
                                    onTap: () => context.push('/orders/${o.id}'),
                                  ),
                                );
                              },
                            ),
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
  static const _commonNext = [
    'CONFIRMED',
    'PROCESSING',
    'PACKED',
    'SHIPPED',
    'OUT_FOR_DELIVERY',
    'DELIVERED',
    'CANCELLED',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrdersProvider>().loadDetail(widget.orderId);
    });
  }

  Future<void> _updateStatus(String next) async {
    final ok = await confirmAction(
      context,
      title: 'Update order status?',
      body: 'Set status to $next. Backend validates the transition.',
      confirmLabel: 'Update',
      danger: next == 'CANCELLED',
    );
    if (!ok || !mounted) return;
    final err = await context.read<OrdersProvider>().updateStatus(
          widget.orderId,
          next,
        );
    if (!mounted) return;
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
          : orders.error != null
              ? OpsError(
                  message: orders.error!,
                  onRetry: () => orders.loadDetail(widget.orderId),
                )
              : detail == null
                  ? const OpsEmpty(title: 'Order not found')
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
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
                        Text(
                          'Payment: ${detail['payment_status'] ?? detail['payment_method'] ?? '—'}',
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
                            children: _commonNext
                                .where((s) => s != detail['status'])
                                .map(
                                  (s) => OutlinedButton(
                                    onPressed: () => _updateStatus(s),
                                    child: Text(s.replaceAll('_', ' ')),
                                  ),
                                )
                                .toList(),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Only valid transitions succeed — invalid ones return an API error.',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ],
                    ),
    );
  }
}
