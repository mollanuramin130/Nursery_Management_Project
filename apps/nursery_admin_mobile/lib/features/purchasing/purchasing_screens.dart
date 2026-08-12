import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

class PurchaseOrdersScreen extends StatefulWidget {
  const PurchaseOrdersScreen({super.key});

  @override
  State<PurchaseOrdersScreen> createState() => _PurchaseOrdersScreenState();
}

class _PurchaseOrdersScreenState extends State<PurchaseOrdersScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PurchasingProvider>().loadPos();
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<PurchasingProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Purchase orders')),
      body: p.loading && p.orders.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : p.error != null
              ? OpsError(message: p.error!, onRetry: p.loadPos)
              : p.orders.isEmpty
                  ? const OpsEmpty(
                      title: 'No purchase orders',
                      subtitle: 'No purchase orders found.',
                    )
                  : RefreshIndicator(
                      onRefresh: p.loadPos,
                      child: ListView.builder(
                        itemCount: p.orders.length,
                        itemBuilder: (context, i) {
                          final o = p.orders[i];
                          return Card(
                            margin: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            child: ListTile(
                              title: Text(o.poNumber),
                              subtitle: Text(
                                '${o.supplier ?? '—'}\n${money(o.grandTotal)} · recv ${o.unitsReceived ?? 0}/${o.unitsOrdered ?? 0}',
                              ),
                              isThreeLine: true,
                              trailing: OpsStatusChip(o.status),
                              onTap: () => context.push('/purchasing/${o.id}'),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

class PurchaseOrderDetailScreen extends StatefulWidget {
  const PurchaseOrderDetailScreen({super.key, required this.poId});

  final int poId;

  @override
  State<PurchaseOrderDetailScreen> createState() =>
      _PurchaseOrderDetailScreenState();
}

class _PurchaseOrderDetailScreenState extends State<PurchaseOrderDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PurchasingProvider>().loadPo(widget.poId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<PurchasingProvider>();
    final auth = context.watch<AuthProvider>();
    final d = p.detail;
    final canReceive = auth.canAny([
          'inventory.adjust',
          'purchase_orders.manage',
          'inventory.receive',
        ]) &&
        (d?['actions'] is Map ? d!['actions']['can_receive'] == true : false);

    return Scaffold(
      appBar: AppBar(title: Text(d?['po_number']?.toString() ?? 'PO')),
      body: p.loading && d == null
          ? const Center(child: CircularProgressIndicator())
          : p.error != null
              ? OpsError(
                  message: p.error!,
                  onRetry: () => p.loadPo(widget.poId),
                )
              : d == null
                  ? const OpsEmpty(title: 'Not found')
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        OpsStatusChip(d['status']?.toString() ?? ''),
                        const SizedBox(height: 8),
                        Text('Supplier: ${d['supplier'] ?? '—'}'),
                        Text('Total: ${money(d['grand_total'] as num?)}'),
                        Text('Expected: ${d['expected_at'] ?? '—'}'),
                        const Divider(height: 28),
                        const Text(
                          'Items',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        ...(d['items'] as List? ?? []).map((raw) {
                          final item = Map<String, dynamic>.from(raw as Map);
                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(item['product_name']?.toString() ?? 'Item'),
                            subtitle: Text(
                              'Ordered ${item['quantity_ordered']} · Received ${item['quantity_received']} · Remaining ${item['quantity_remaining']}',
                            ),
                          );
                        }),
                        if (canReceive) ...[
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: () =>
                                context.push('/purchasing/${widget.poId}/receive'),
                            child: const Text('Receive goods'),
                          ),
                        ],
                      ],
                    ),
    );
  }
}

class ReceiveGoodsScreen extends StatefulWidget {
  const ReceiveGoodsScreen({super.key, required this.poId});

  final int poId;

  @override
  State<ReceiveGoodsScreen> createState() => _ReceiveGoodsScreenState();
}

class _ReceiveGoodsScreenState extends State<ReceiveGoodsScreen> {
  final Map<int, TextEditingController> _qty = {};
  final Map<int, TextEditingController> _damaged = {};
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<PurchasingProvider>().loadPo(widget.poId);
      final items = context.read<PurchasingProvider>().detail?['items'] as List? ?? [];
      for (final raw in items) {
        final item = Map<String, dynamic>.from(raw as Map);
        final id = (item['id'] as num).toInt();
        final remaining = (item['quantity_remaining'] as num?)?.toInt() ?? 0;
        _qty[id] = TextEditingController(text: remaining > 0 ? '$remaining' : '0');
        _damaged[id] = TextEditingController(text: '0');
      }
      setState(() {});
    });
  }

  @override
  void dispose() {
    for (final c in _qty.values) {
      c.dispose();
    }
    for (final c in _damaged.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<PurchasingProvider>();
    final d = p.detail;
    final items = (d?['items'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Receive goods')),
      body: d == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(d['po_number']?.toString() ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                ...items.map((item) {
                  final id = (item['id'] as num).toInt();
                  _qty.putIfAbsent(
                    id,
                    () => TextEditingController(
                      text: '${item['quantity_remaining'] ?? 0}',
                    ),
                  );
                  _damaged.putIfAbsent(
                    id,
                    () => TextEditingController(text: '0'),
                  );
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item['product_name']?.toString() ?? 'Item',
                              style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text(
                            'Remaining ${item['quantity_remaining']}',
                            style: const TextStyle(color: OpsColors.muted),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _qty[id],
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Receive qty',
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _damaged[id],
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Damaged qty',
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          final payload = <Map<String, dynamic>>[];
                          for (final item in items) {
                            final id = (item['id'] as num).toInt();
                            final q = int.tryParse(_qty[id]?.text ?? '0') ?? 0;
                            final dmg =
                                int.tryParse(_damaged[id]?.text ?? '0') ?? 0;
                            if (q > 0) {
                              payload.add({
                                'purchase_order_item_id': id,
                                'quantity': q,
                                'damaged': dmg,
                              });
                            }
                          }
                          if (payload.isEmpty) return;
                          final ok = await confirmAction(
                            context,
                            title: 'Confirm receiving?',
                            body:
                                'Receive ${payload.length} line(s). Inventory increases only after API success.',
                          );
                          if (!ok || !mounted) return;
                          setState(() => _busy = true);
                          final err = await p.receive(widget.poId, payload);
                          setState(() => _busy = false);
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(err ?? 'Goods received')),
                          );
                          if (err == null) context.pop();
                        },
                  child: _busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Confirm receive'),
                ),
              ],
            ),
    );
  }
}

class SuppliersScreen extends StatefulWidget {
  const SuppliersScreen({super.key});

  @override
  State<SuppliersScreen> createState() => _SuppliersScreenState();
}

class _SuppliersScreenState extends State<SuppliersScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PurchasingProvider>().loadSuppliers();
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<PurchasingProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Suppliers')),
      body: p.loading && p.suppliers.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : p.error != null
              ? OpsError(message: p.error!, onRetry: p.loadSuppliers)
              : ListView.builder(
                  itemCount: p.suppliers.length,
                  itemBuilder: (context, i) {
                    final s = p.suppliers[i];
                    return ListTile(
                      title: Text(s['name']?.toString() ?? ''),
                      subtitle: Text(
                        '${s['code'] ?? ''} · ${s['phone'] ?? s['email'] ?? '—'}\n'
                        'Products ${s['product_count'] ?? 0} · POs ${s['purchase_order_count'] ?? 0}',
                      ),
                      isThreeLine: true,
                      trailing: OpsStatusChip(s['status']?.toString() ?? ''),
                    );
                  },
                ),
    );
  }
}

class WarehousesScreen extends StatefulWidget {
  const WarehousesScreen({super.key});

  @override
  State<WarehousesScreen> createState() => _WarehousesScreenState();
}

class _WarehousesScreenState extends State<WarehousesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PurchasingProvider>().loadWarehouses();
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<PurchasingProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Warehouses')),
      body: p.loading && p.warehouses.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : p.error != null
              ? OpsError(message: p.error!, onRetry: p.loadWarehouses)
              : ListView.builder(
                  itemCount: p.warehouses.length,
                  itemBuilder: (context, i) {
                    final w = p.warehouses[i];
                    return ListTile(
                      title: Text('${w['code']} — ${w['name']}'),
                      subtitle: Text(w['city']?.toString() ?? ''),
                      trailing: OpsStatusChip(w['status']?.toString() ?? ''),
                      onTap: () => context.go('/inventory'),
                    );
                  },
                ),
    );
  }
}
