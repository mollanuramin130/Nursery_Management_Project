import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class SubscriptionsScreen extends StatefulWidget {
  const SubscriptionsScreen({super.key});

  @override
  State<SubscriptionsScreen> createState() => _SubscriptionsScreenState();
}

class _SubscriptionsScreenState extends State<SubscriptionsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final api = context.read<ApiClient>();
    final data = await api.getData(
      '/subscriptions',
      query: {'per_page': 30},
      map: (d) => d,
    );
    if (data is List) {
      return data
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return [];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Subscriptions')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(child: Text(snap.error.toString()));
          }
          final rows = snap.data ?? [];
          if (rows.isEmpty) {
            return const Padding(
              padding: EdgeInsets.all(AppSpace.lg),
              child: EmptyStateView(
                title: 'No subscriptions',
                message:
                    'Subscribe from a product with an active plan. Each cycle creates a normal order you pay for.',
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(AppSpace.screen),
              itemCount: rows.length,
              separatorBuilder: (_, __) => const SizedBox(height: AppSpace.sm),
              itemBuilder: (context, i) {
                final s = rows[i];
                return ListTile(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadii.lg),
                    side: const BorderSide(color: AppColors.border),
                  ),
                  title: Text(s['subscription_number']?.toString() ?? ''),
                  subtitle: Text(
                    '${s['product_name'] ?? ''} · ${s['frequency'] ?? ''} · ${s['status'] ?? ''}',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () =>
                      context.push('/account/subscriptions/${s['id']}'),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class SubscriptionDetailScreen extends StatefulWidget {
  const SubscriptionDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<SubscriptionDetailScreen> createState() =>
      _SubscriptionDetailScreenState();
}

class _SubscriptionDetailScreenState extends State<SubscriptionDetailScreen> {
  late Future<Map<String, dynamic>> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final api = context.read<ApiClient>();
    return api.getData(
      '/subscriptions/${widget.id}',
      map: (d) => Map<String, dynamic>.from(d as Map),
    );
  }

  Future<void> _act(String path, {Map<String, dynamic>? body}) async {
    setState(() => _busy = true);
    try {
      final api = context.read<ApiClient>();
      await api.sendData(
        'POST',
        '/subscriptions/${widget.id}/$path',
        body: body ?? {},
        map: (d) => d,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Updated')));
      setState(() => _future = _load());
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Subscription')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || snap.data == null) {
            return Center(child: Text(snap.error?.toString() ?? 'Not found'));
          }
          final s = snap.data!;
          final actions = s['actions'] is Map
              ? Map<String, dynamic>.from(s['actions'] as Map)
              : <String, dynamic>{};
          final cycles = (s['cycles'] is List)
              ? (s['cycles'] as List)
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList()
              : <Map<String, dynamic>>[];

          return ListView(
            padding: const EdgeInsets.all(AppSpace.screen),
            children: [
              Text(
                s['subscription_number']?.toString() ?? '',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                '${s['product_name'] ?? ''} · ${s['frequency'] ?? ''} · ${s['status'] ?? ''}',
              ),
              const SizedBox(height: 8),
              Text(
                'Locked price × qty: ${s['unit_price']} × ${s['quantity']}',
                style: const TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: 4),
              const Text(
                'Pay per cycle — cards are not auto-charged.',
                style: TextStyle(color: AppColors.muted, fontSize: 12),
              ),
              const SizedBox(height: AppSpace.lg),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (actions['can_pause'] == true)
                    FilledButton(
                      onPressed: _busy ? null : () => _act('pause'),
                      child: const Text('Pause'),
                    ),
                  if (actions['can_resume'] == true)
                    FilledButton(
                      onPressed: _busy ? null : () => _act('resume'),
                      child: const Text('Resume'),
                    ),
                  if (actions['can_cancel'] == true)
                    OutlinedButton(
                      onPressed: _busy
                          ? null
                          : () async {
                              final ok = await showDialog<bool>(
                                context: context,
                                builder: (ctx) => AlertDialog(
                                  title: const Text('Cancel subscription?'),
                                  content: const Text(
                                    'Existing orders stay as-is.',
                                  ),
                                  actions: [
                                    TextButton(
                                      onPressed: () =>
                                          Navigator.pop(ctx, false),
                                      child: const Text('Keep'),
                                    ),
                                    TextButton(
                                      onPressed: () => Navigator.pop(ctx, true),
                                      child: const Text('Cancel plan'),
                                    ),
                                  ],
                                ),
                              );
                              if (ok == true) {
                                await _act(
                                  'cancel',
                                  body: {'reason': 'Customer cancelled'},
                                );
                              }
                            },
                      child: const Text('Cancel'),
                    ),
                ],
              ),
              const SizedBox(height: AppSpace.xl),
              Text('Cycles', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              ...cycles.map(
                (c) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    'Cycle #${c['cycle_number']} · ${c['status']}',
                  ),
                  subtitle: c['order_id'] != null
                      ? Text('Order #${c['order_id']}')
                      : null,
                  trailing: Text('${c['amount'] ?? ''}'),
                  onTap: c['order_id'] != null
                      ? () => context.push('/orders/${c['order_id']}')
                      : null,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
