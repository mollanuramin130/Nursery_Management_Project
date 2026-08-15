import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/customer_account_models.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// QA-PAR-001 — My Returns list (`GET /customer/returns`).
class ReturnsScreen extends StatefulWidget {
  const ReturnsScreen({super.key});

  @override
  State<ReturnsScreen> createState() => _ReturnsScreenState();
}

class _ReturnsScreenState extends State<ReturnsScreen> {
  late Future<List<CustomerReturnSummary>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<CustomerReturnSummary>> _load() async {
    final api = context.read<ApiClient>();
    final rows = await api.getData(
      '/customer/returns',
      query: {'per_page': 30},
      map: (d) => parseAccountListPayload(d)
          .map(CustomerReturnSummary.fromJson)
          .toList(),
    );
    return rows;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    if (!auth.bootstrapped) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (auth.user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Returns')),
        body: Padding(
          padding: const EdgeInsets.all(AppSpace.screen),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 48),
              Text(
                'Sign in to view return requests',
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              Text(
                'Return requests for delivered orders appear here.',
                style: Theme.of(context).textTheme.bodyMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              AppButton(
                label: 'Sign in',
                expanded: true,
                onPressed: () => AuthNavigation.pushLogin(
                  context,
                  redirect: '/account/returns',
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Returns')),
      body: FutureBuilder<List<CustomerReturnSummary>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load returns',
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
            );
          }
          final items = snap.data ?? const [];
          if (items.isEmpty) {
            return EmptyStateView(
              title: 'No return requests',
              message:
                  'When you return an item from a delivered order, it will show up here.',
              actionLabel: 'View orders',
              onAction: () => context.go('/orders'),
            );
          }
          return RefreshIndicator(
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
            child: ListView.separated(
              padding: const EdgeInsets.all(AppSpace.screen),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, i) {
                final r = items[i];
                return AppSurfaceCard(
                  onTap: () => context.push('/account/returns/${r.id}'),
                  padding: const EdgeInsets.all(AppSpace.lg),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Return #${r.id}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [
                                if (r.orderNumber != null)
                                  'Order ${r.orderNumber}'
                                else
                                  'Order #${r.orderId}',
                                if (r.createdAt != null)
                                  formatOrderDate(r.createdAt),
                              ].join(' · '),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if (r.items.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                r.items
                                    .map((e) => e.name ?? 'Item')
                                    .take(2)
                                    .join(', '),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ],
                          ],
                        ),
                      ),
                      AppBadge(
                        label: r.statusLabel,
                        tone: r.status == 'COMPLETED' || r.status == 'APPROVED'
                            ? AppBadgeTone.success
                            : r.status == 'REJECTED' || r.status == 'CANCELLED'
                                ? AppBadgeTone.error
                                : AppBadgeTone.brand,
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
