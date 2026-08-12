import 'package:flutter/material.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class RewardsScreen extends StatefulWidget {
  const RewardsScreen({super.key});

  @override
  State<RewardsScreen> createState() => _RewardsScreenState();
}

class _RewardsScreenState extends State<RewardsScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final api = context.read<ApiClient>();
    final account = await api.getData(
      '/customer/loyalty',
      map: (d) => Map<String, dynamic>.from(d as Map),
    );
    final history = await api.getData(
      '/customer/loyalty/transactions',
      query: {'per_page': 30},
      map: (d) => d,
    );
    final txs = history is List
        ? history
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList()
        : <Map<String, dynamic>>[];
    return {'account': account, 'transactions': txs};
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rewards')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || !snap.hasData) {
            return ErrorStateView(
              title: 'Unable to load rewards',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final account =
              Map<String, dynamic>.from(snap.data!['account'] as Map);
          final txs = (snap.data!['transactions'] as List)
              .cast<Map<String, dynamic>>();
          final how =
              (account['how_it_works'] as List?)
                  ?.map((e) => e.toString())
                  .toList() ??
              const <String>[];

          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Current balance',
                          style: Theme.of(context).textTheme.labelLarge
                              ?.copyWith(color: AppColors.muted),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '${account['balance'] ?? 0}',
                          style: Theme.of(context).textTheme.displaySmall
                              ?.copyWith(
                                fontWeight: FontWeight.w800,
                                color: AppColors.primaryDeep,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Lifetime earned ${account['lifetime_earned'] ?? 0} · '
                          'Redeemed/reversed ${account['lifetime_redeemed'] ?? 0}',
                          style: const TextStyle(color: AppColors.muted),
                        ),
                      ],
                    ),
                  ),
                ),
                if (how.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'How it works',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  ...how.map(
                    (line) => Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('• '),
                          Expanded(child: Text(line)),
                        ],
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                Text(
                  'Points history',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                if (txs.isEmpty)
                  const Text(
                    'No points yet. Points are awarded when an order is delivered.',
                    style: TextStyle(color: AppColors.muted),
                  )
                else
                  ...txs.map((t) {
                    final points = (t['points'] as num?)?.toInt() ?? 0;
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '${t['type'] ?? ''} · ${points > 0 ? '+$points' : points}',
                      ),
                      subtitle: Text(t['reason']?.toString() ?? ''),
                      trailing: Text(
                        'Bal ${t['balance_after'] ?? ''}',
                        style: const TextStyle(color: AppColors.muted),
                      ),
                    );
                  }),
              ],
            ),
          );
        },
      ),
    );
  }
}
