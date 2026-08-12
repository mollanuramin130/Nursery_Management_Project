import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/models/models.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

class InventoryListScreen extends StatefulWidget {
  const InventoryListScreen({super.key, this.lowOnly = false});

  final bool lowOnly;

  @override
  State<InventoryListScreen> createState() => _InventoryListScreenState();
}

class _InventoryListScreenState extends State<InventoryListScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final inv = context.read<InventoryProvider>();
      inv.lowOnly = widget.lowOnly;
      inv.load(lowStock: widget.lowOnly);
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
    final inv = context.watch<InventoryProvider>();
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.lowOnly ? 'Low stock' : 'Inventory'),
        actions: [
          IconButton(
            tooltip: 'Scan SKU',
            onPressed: () => context.push('/inventory/scan'),
            icon: const Icon(Icons.qr_code_scanner),
          ),
          if (auth.can('inventory.view'))
            IconButton(
              tooltip: 'Movements',
              onPressed: () => context.push('/inventory/movements'),
              icon: const Icon(Icons.history),
            ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _search,
              decoration: const InputDecoration(
                hintText: 'Search product or SKU',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 400), () {
                  inv.q = v;
                  inv.load(lowStock: widget.lowOnly);
                });
              },
            ),
          ),
          Expanded(
            child: inv.loading && inv.rows.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : inv.error != null
                    ? OpsError(message: inv.error!, onRetry: inv.load)
                    : inv.rows.isEmpty
                        ? OpsEmpty(
                            title: widget.lowOnly
                                ? 'No low stock'
                                : 'No inventory',
                            subtitle: widget.lowOnly
                                ? 'Inventory is currently healthy.'
                                : 'No rows match your search.',
                          )
                        : RefreshIndicator(
                            onRefresh: () => inv.load(lowStock: widget.lowOnly),
                            child: ListView.builder(
                              itemCount: inv.rows.length,
                              itemBuilder: (context, i) {
                                final r = inv.rows[i];
                                return Card(
                                  margin: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 6,
                                  ),
                                  child: ListTile(
                                    title: Text(r.productName),
                                    subtitle: Text(
                                      '${r.sku} · ${r.warehouseCode ?? '—'}\n'
                                      'On hand ${r.qtyOnHand} · Reserved ${r.qtyReserved} · Avail ${r.sellable}',
                                    ),
                                    isThreeLine: true,
                                    trailing: OpsStatusChip(r.stockStatus),
                                    onTap: () =>
                                        context.push('/inventory/${r.id}'),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class InventoryDetailScreen extends StatefulWidget {
  const InventoryDetailScreen({super.key, required this.itemId});

  final int itemId;

  @override
  State<InventoryDetailScreen> createState() => _InventoryDetailScreenState();
}

class _InventoryDetailScreenState extends State<InventoryDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InventoryProvider>().loadItem(widget.itemId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final inv = context.watch<InventoryProvider>();
    final auth = context.watch<AuthProvider>();
    final d = inv.selectedDetail;
    return Scaffold(
      appBar: AppBar(title: Text(d?['product_name']?.toString() ?? 'Stock')),
      body: inv.loading && d == null
          ? const Center(child: CircularProgressIndicator())
          : inv.error != null
              ? OpsError(
                  message: inv.error!,
                  onRetry: () => inv.loadItem(widget.itemId),
                )
              : d == null
                  ? const OpsEmpty(title: 'Not found')
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Text(d['sku']?.toString() ?? '',
                            style: const TextStyle(color: OpsColors.muted)),
                        const SizedBox(height: 8),
                        OpsStatusChip(d['stock_status']?.toString() ?? ''),
                        const SizedBox(height: 16),
                        _kv('On hand', '${d['qty_on_hand']}'),
                        _kv('Reserved', '${d['qty_reserved']}'),
                        _kv('Damaged', '${d['qty_damaged'] ?? 0}'),
                        _kv('Available', '${d['sellable']}'),
                        _kv('Threshold', '${d['low_stock_threshold']}'),
                        _kv('Warehouse', '${d['warehouse_code'] ?? '—'}'),
                        const SizedBox(height: 20),
                        if (auth.can('inventory.adjust'))
                          FilledButton(
                            onPressed: () => context.push(
                              '/inventory/${widget.itemId}/adjust',
                            ),
                            child: const Text('Adjust stock'),
                          ),
                        const SizedBox(height: 12),
                        OutlinedButton(
                          onPressed: () => context.push('/inventory/movements'),
                          child: const Text('View movements'),
                        ),
                      ],
                    ),
    );
  }

  Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(child: Text(k, style: const TextStyle(color: OpsColors.muted))),
            Text(v, style: const TextStyle(fontWeight: FontWeight.w700)),
          ],
        ),
      );
}

class AdjustStockScreen extends StatefulWidget {
  const AdjustStockScreen({super.key, required this.itemId});

  final int itemId;

  @override
  State<AdjustStockScreen> createState() => _AdjustStockScreenState();
}

class _AdjustStockScreenState extends State<AdjustStockScreen> {
  final _qty = TextEditingController(text: '1');
  final _note = TextEditingController();
  String _mode = 'remove';
  String _reason = 'loss';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InventoryProvider>().loadItem(widget.itemId);
    });
  }

  @override
  void dispose() {
    _qty.dispose();
    _note.dispose();
    super.dispose();
  }

  int _delta(InventoryRow row) {
    final n = int.tryParse(_qty.text) ?? 0;
    if (_mode == 'add') return n;
    if (_mode == 'remove') return -n;
    return n - row.qtyOnHand;
  }

  @override
  Widget build(BuildContext context) {
    final inv = context.watch<InventoryProvider>();
    final row = inv.selected;
    return Scaffold(
      appBar: AppBar(title: const Text('Adjust stock')),
      body: row == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(row.productName,
                    style: const TextStyle(fontWeight: FontWeight.w800)),
                Text('${row.sku} · ${row.warehouseCode}'),
                const SizedBox(height: 12),
                Text('Current on hand: ${row.qtyOnHand}'),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _mode,
                  decoration: const InputDecoration(labelText: 'Adjustment type'),
                  items: const [
                    DropdownMenuItem(value: 'add', child: Text('Add')),
                    DropdownMenuItem(value: 'remove', child: Text('Remove')),
                    DropdownMenuItem(value: 'set', child: Text('Set to')),
                  ],
                  onChanged: (v) => setState(() => _mode = v ?? 'remove'),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: _reason,
                  decoration: const InputDecoration(labelText: 'Reason'),
                  items: const [
                    DropdownMenuItem(value: 'purchase', child: Text('Purchase / found')),
                    DropdownMenuItem(value: 'loss', child: Text('Loss')),
                    DropdownMenuItem(value: 'damaged', child: Text('Damaged')),
                    DropdownMenuItem(value: 'reconciliation', child: Text('Counting correction')),
                    DropdownMenuItem(value: 'adjust', child: Text('Manual correction')),
                  ],
                  onChanged: (v) => setState(() => _reason = v ?? 'loss'),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _qty,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Quantity'),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _note,
                  decoration: const InputDecoration(labelText: 'Notes'),
                  maxLines: 2,
                ),
                const SizedBox(height: 16),
                Text(
                  'Resulting on hand (preview): ${row.qtyOnHand + _delta(row)}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Backend validates and remains authoritative.',
                  style: TextStyle(fontSize: 12, color: OpsColors.muted),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          final delta = _delta(row);
                          if (delta == 0) return;
                          final ok = await confirmAction(
                            context,
                            title: 'Confirm adjustment?',
                            body:
                                'Current: ${row.qtyOnHand}\nAdjustment: $delta\nResult: ${row.qtyOnHand + delta}',
                            danger: delta < 0,
                          );
                          if (!ok || !mounted) return;
                          setState(() => _busy = true);
                          final err = await inv.adjust(
                            warehouseId: row.warehouseId!,
                            productId: row.productId,
                            adjustment: delta,
                            reason: _reason,
                            note: _note.text,
                            idempotencyKey:
                                'ops-${row.id}-${DateTime.now().millisecondsSinceEpoch}',
                          );
                          setState(() => _busy = false);
                          if (!mounted) return;
                          if (err != null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text(err)),
                            );
                            return;
                          }
                          await inv.loadItem(widget.itemId);
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Stock adjusted')),
                          );
                          context.pop();
                        },
                  child: _busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Confirm adjustment'),
                ),
              ],
            ),
    );
  }
}

class MovementsScreen extends StatefulWidget {
  const MovementsScreen({super.key});

  @override
  State<MovementsScreen> createState() => _MovementsScreenState();
}

class _MovementsScreenState extends State<MovementsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InventoryProvider>().loadMovements();
    });
  }

  @override
  Widget build(BuildContext context) {
    final inv = context.watch<InventoryProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Stock movements')),
      body: inv.loading && inv.movements.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : inv.error != null
              ? OpsError(message: inv.error!, onRetry: inv.loadMovements)
              : inv.movements.isEmpty
                  ? const OpsEmpty(title: 'No movements')
                  : ListView.builder(
                      itemCount: inv.movements.length,
                      itemBuilder: (context, i) {
                        final m = inv.movements[i];
                        final delta = m['qty_delta'];
                        return ListTile(
                          title: Text('${m['type']} · $delta'),
                          subtitle: Text(
                            'Before ${m['qty_before'] ?? '—'} → After ${m['qty_after'] ?? '—'}\n'
                            '${m['reference_type'] ?? ''} ${m['reference_id'] ?? ''} · ${m['created_at'] ?? ''}',
                          ),
                          isThreeLine: true,
                        );
                      },
                    ),
    );
  }
}

class ScanSkuScreen extends StatefulWidget {
  const ScanSkuScreen({super.key});

  @override
  State<ScanSkuScreen> createState() => _ScanSkuScreenState();
}

class _ScanSkuScreenState extends State<ScanSkuScreen> {
  bool _handling = false;
  final _manual = TextEditingController();

  Future<void> _lookup(String raw) async {
    if (_handling) return;
    final code = raw.trim();
    if (code.isEmpty) return;
    setState(() => _handling = true);
    try {
      final row = await context.read<InventoryProvider>().findBySku(code);
      if (!mounted) return;
      if (row == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No product for “$code”')),
        );
      } else {
        context.push('/inventory/${row.id}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _handling = false);
    }
  }

  @override
  void dispose() {
    _manual.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Scan / lookup SKU')),
      body: Column(
        children: [
          Expanded(
            child: MobileScanner(
              onDetect: (capture) {
                final barcodes = capture.barcodes;
                if (barcodes.isEmpty) return;
                final v = barcodes.first.rawValue;
                if (v != null) _lookup(v);
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _manual,
                    decoration: const InputDecoration(
                      hintText: 'Or type SKU (no barcode field required)',
                    ),
                    onSubmitted: _lookup,
                  ),
                ),
                const SizedBox(width: 8),
                FilledButton(
                  onPressed: _handling ? null : () => _lookup(_manual.text),
                  child: const Text('Find'),
                ),
              ],
            ),
          ),
          const Padding(
            padding: EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: Text(
              'Scanned values are treated as SKU search. Dedicated barcode column is not in the product schema — documented gap.',
              style: TextStyle(fontSize: 12, color: OpsColors.muted),
            ),
          ),
        ],
      ),
    );
  }
}
