import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

class FulfillmentHubScreen extends StatelessWidget {
  const FulfillmentHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (!auth.can('fulfillment.view')) {
      return Scaffold(
        appBar: AppBar(title: const Text('Fulfillment')),
        body: const OpsEmpty(
          title: 'No access',
          subtitle: 'Missing fulfillment.view permission.',
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Fulfillment')),
      body: ListView(
        children: [
          ListTile(
            leading: const Icon(Icons.inventory_outlined),
            title: const Text('Picking queue'),
            onTap: () => context.push('/fulfillment/queue/picking'),
          ),
          ListTile(
            leading: const Icon(Icons.inventory_2_outlined),
            title: const Text('Packing queue'),
            onTap: () => context.push('/fulfillment/queue/packing'),
          ),
          ListTile(
            leading: const Icon(Icons.local_shipping_outlined),
            title: const Text('Ready to ship'),
            onTap: () => context.push('/fulfillment/queue/ready_to_ship'),
          ),
          ListTile(
            leading: const Icon(Icons.delivery_dining_outlined),
            title: const Text('In transit'),
            onTap: () => context.push('/fulfillment/queue/in_transit'),
          ),
          ListTile(
            leading: const Icon(Icons.warning_amber_outlined),
            title: const Text('Exceptions'),
            onTap: () => context.push('/fulfillment/queue/exceptions'),
          ),
        ],
      ),
    );
  }
}

class FulfillmentQueueScreen extends StatefulWidget {
  const FulfillmentQueueScreen({super.key, required this.queue});

  final String queue;

  @override
  State<FulfillmentQueueScreen> createState() => _FulfillmentQueueScreenState();
}

class _FulfillmentQueueScreenState extends State<FulfillmentQueueScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final data = await api.getData(
        '/admin/fulfillment/queue/${widget.queue}',
        map: (d) => (d as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
      setState(() {
        _rows = data;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.userMessage : e.toString();
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.queue.replaceAll('_', ' ');
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? ListView(
                    children: [
                      OpsError(message: _error!, onRetry: _load),
                    ],
                  )
                : _rows.isEmpty
                    ? ListView(
                        children: const [
                          OpsEmpty(
                            title: 'Queue empty',
                            subtitle: 'No orders in this queue.',
                          ),
                        ],
                      )
                    : ListView.separated(
                        itemCount: _rows.length,
                        separatorBuilder: (context, index) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final r = _rows[i];
                          return ListTile(
                            title: Text('${r['order_number'] ?? r['id']}'),
                            subtitle: Text(
                              '${r['customer'] ?? ''} · ${r['status'] ?? ''}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => context.push(
                              '/fulfillment/orders/${r['id']}',
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}

class FulfillmentOrderScreen extends StatefulWidget {
  const FulfillmentOrderScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<FulfillmentOrderScreen> createState() => _FulfillmentOrderScreenState();
}

class _FulfillmentOrderScreenState extends State<FulfillmentOrderScreen> {
  bool _loading = true;
  bool _busy = false;
  String? _error;
  Map<String, dynamic>? _order;
  final _scanCtrl = TextEditingController();
  final _podCtrl = TextEditingController();
  final _etaCtrl = TextEditingController();

  Map<String, dynamic> get _actions =>
      Map<String, dynamic>.from((_order?['actions'] as Map?) ?? {});

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _scanCtrl.dispose();
    _podCtrl.dispose();
    _etaCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final data = await api.getData(
        '/admin/fulfillment/orders/${widget.orderId}',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      setState(() {
        _order = data;
        final eta = (_order?['shipment'] as Map?)?['eta_date'];
        if (eta != null) _etaCtrl.text = '$eta';
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.userMessage : e.toString();
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _run(Future<void> Function() action, String ok) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ok)));
      await _load();
    } catch (e) {
      if (!mounted) return;
      final msg = e is ApiException ? e.userMessage : e.toString();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _post(String path, [Map<String, dynamic>? body]) async {
    final api = context.read<ApiClient>();
    await api.sendData(
      'POST',
      path,
      body: body ?? {},
      map: (_) => true,
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Fulfillment')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null || _order == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Fulfillment')),
        body: OpsError(message: _error ?? 'Not found', onRetry: _load),
      );
    }

    final order = _order!;
    final items = (order['items'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    final shipment = order['shipment'] is Map
        ? Map<String, dynamic>.from(order['shipment'] as Map)
        : null;

    return Scaffold(
      appBar: AppBar(
        title: Text('${order['order_number']}'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            '${order['status']}',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: OpsColors.brand,
            ),
          ),
          Text('${(order['customer'] as Map?)?['name'] ?? ''}'),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (_actions['can_start_picking'] == true &&
                  auth.can('fulfillment.pick'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/pick/start',
                            ),
                            'Picking started',
                          ),
                  child: const Text('Start pick'),
                ),
              if (_actions['can_complete_pick'] == true &&
                  auth.can('fulfillment.pick'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/pick/complete',
                            ),
                            'Pick complete',
                          ),
                  child: const Text('Complete pick'),
                ),
              if (_actions['can_pack'] == true && auth.can('fulfillment.pack'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/pack',
                              {'package_count': 1},
                            ),
                            'Packed',
                          ),
                  child: const Text('Pack'),
                ),
              if (_actions['can_ship'] == true && auth.can('fulfillment.ship'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/ship',
                              {},
                            ),
                            'Shipped',
                          ),
                  child: const Text('Ship'),
                ),
              if (_actions['can_out_for_delivery'] == true &&
                  auth.can('fulfillment.ship'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/out-for-delivery',
                            ),
                            'Out for delivery',
                          ),
                  child: const Text('Out for delivery'),
                ),
              if (_actions['can_deliver'] == true &&
                  auth.can('fulfillment.ship'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/deliver',
                              {
                                if (_podCtrl.text.trim().isNotEmpty)
                                  'method': 'note',
                                if (_podCtrl.text.trim().isNotEmpty)
                                  'note': _podCtrl.text.trim(),
                              },
                            ),
                            'Delivered',
                          ),
                  child: const Text('Deliver'),
                ),
              if (_actions['can_fail_delivery'] == true &&
                  auth.can('fulfillment.ship'))
                OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/fail-delivery',
                              {
                                'reason': 'CUSTOMER_UNAVAILABLE',
                                'note': 'Mobile fail',
                              },
                            ),
                            'Failure recorded',
                          ),
                  child: const Text('Fail delivery'),
                ),
              if (_actions['can_retry_delivery'] == true &&
                  auth.can('fulfillment.ship'))
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                            () => _post(
                              '/admin/fulfillment/orders/${widget.orderId}/retry-delivery',
                            ),
                            'Retry started',
                          ),
                  child: const Text('Retry'),
                ),
            ],
          ),
          if (_actions['can_scan_pick'] == true &&
              auth.can('fulfillment.pick')) ...[
            const SizedBox(height: 16),
            TextField(
              controller: _scanCtrl,
              decoration: const InputDecoration(
                labelText: 'Scan SKU',
                border: OutlineInputBorder(),
              ),
              textInputAction: TextInputAction.done,
              onSubmitted: (_) {
                if (_scanCtrl.text.trim().isEmpty) return;
                _run(() async {
                  await _post(
                    '/admin/fulfillment/orders/${widget.orderId}/pick/scan',
                    {
                      'code': _scanCtrl.text.trim(),
                      'increment_by': 1,
                    },
                  );
                  _scanCtrl.clear();
                }, 'Scan OK');
              },
            ),
            const SizedBox(height: 8),
            FilledButton.tonal(
              onPressed: _busy || _scanCtrl.text.trim().isEmpty
                  ? null
                  : () => _run(() async {
                        await _post(
                          '/admin/fulfillment/orders/${widget.orderId}/pick/scan',
                          {
                            'code': _scanCtrl.text.trim(),
                            'increment_by': 1,
                          },
                        );
                        _scanCtrl.clear();
                      }, 'Scan OK'),
              child: const Text('Verify scan (+1)'),
            ),
          ],
          if (_actions['can_deliver'] == true) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _podCtrl,
              decoration: const InputDecoration(
                labelText: 'POD note (optional)',
                border: OutlineInputBorder(),
              ),
            ),
          ],
          if (_actions['can_reschedule'] == true &&
              auth.can('fulfillment.ship')) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _etaCtrl,
              decoration: const InputDecoration(
                labelText: 'Reschedule ETA (YYYY-MM-DD)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: _busy || _etaCtrl.text.trim().isEmpty
                  ? null
                  : () => _run(
                        () => _post(
                          '/admin/fulfillment/orders/${widget.orderId}/reschedule',
                          {'eta_date': _etaCtrl.text.trim()},
                        ),
                        'Rescheduled',
                      ),
              child: const Text('Save ETA'),
            ),
          ],
          const SizedBox(height: 20),
          Text('Lines', style: Theme.of(context).textTheme.titleMedium),
          ...items.map(
            (it) => ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('${it['name']}'),
              subtitle: Text('SKU ${it['sku']}'),
              trailing: Text('${it['picked']}/${it['required']}'),
            ),
          ),
          if (shipment != null) ...[
            const Divider(height: 32),
            Text('Shipment', style: Theme.of(context).textTheme.titleMedium),
            Text('${shipment['carrier']} · ${shipment['tracking_number']}'),
            Text('Status: ${shipment['status']}'),
            if (shipment['assigned_driver'] is Map)
              Text(
                'Driver: ${(shipment['assigned_driver'] as Map)['name']}',
              ),
            if (shipment['eta_date'] != null)
              Text('ETA: ${shipment['eta_date']}'),
          ],
        ],
      ),
    );
  }
}
