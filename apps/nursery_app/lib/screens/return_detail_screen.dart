import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/customer_account_models.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// QA-PAR-001 — Return detail (`GET /returns/{id}`).
class ReturnDetailScreen extends StatefulWidget {
  const ReturnDetailScreen({super.key, required this.returnId});

  final int returnId;

  @override
  State<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

class _ReturnDetailScreenState extends State<ReturnDetailScreen> {
  late Future<CustomerReturnSummary> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<CustomerReturnSummary> _load() async {
    final api = context.read<ApiClient>();
    return api.getData(
      '/returns/${widget.returnId}',
      map: (d) => CustomerReturnSummary.fromJson(
        Map<String, dynamic>.from(d as Map),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Return #${widget.returnId}')),
      body: FutureBuilder<CustomerReturnSummary>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || !snap.hasData) {
            return ErrorStateView(
              title: 'Unable to load return',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final r = snap.data!;
          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: ListView(
              padding: const EdgeInsets.all(AppSpace.screen),
              children: [
                AppSurfaceCard(
                  padding: const EdgeInsets.all(AppSpace.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Return #${r.id}',
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                          AppBadge(label: r.statusLabel),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        r.orderNumber != null
                            ? 'Order ${r.orderNumber}'
                            : 'Order #${r.orderId}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      if (r.createdAt != null)
                        Text(
                          'Requested ${formatOrderDate(r.createdAt)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      if (r.notes != null && r.notes!.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text(
                          'Notes',
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        Text(r.notes!),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpace.lg),
                Text(
                  'Items',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                if (r.items.isEmpty)
                  Text(
                    'No item details',
                    style: Theme.of(context).textTheme.bodySmall,
                  )
                else
                  ...r.items.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AppSurfaceCard(
                        padding: const EdgeInsets.all(AppSpace.md),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.name ?? 'Item',
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            Text(
                              'Qty ${item.quantity}'
                              '${item.reason != null ? ' · ${item.reason}' : ''}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: AppSpace.xl),
                AppButton(
                  label: 'View order',
                  expanded: true,
                  variant: AppButtonVariant.secondary,
                  onPressed: () => context.push('/orders/${r.orderId}'),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => context.push('/account/returns'),
                  child: const Text('Back to returns'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
