import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/customer_account_models.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// QA-PAR-002 — My Reviews (`GET /customer/reviews`).
class MyReviewsScreen extends StatefulWidget {
  const MyReviewsScreen({super.key});

  @override
  State<MyReviewsScreen> createState() => _MyReviewsScreenState();
}

class _MyReviewsScreenState extends State<MyReviewsScreen> {
  late Future<List<CustomerMyReview>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<CustomerMyReview>> _load() async {
    final api = context.read<ApiClient>();
    return api.getData(
      '/customer/reviews',
      query: {'per_page': 30},
      map: (d) => parseAccountListPayload(d)
          .map(CustomerMyReview.fromJson)
          .toList(),
    );
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
        appBar: AppBar(title: const Text('My reviews')),
        body: Padding(
          padding: const EdgeInsets.all(AppSpace.screen),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 48),
              Text(
                'Sign in to see your reviews',
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              AppButton(
                label: 'Sign in',
                expanded: true,
                onPressed: () => AuthNavigation.pushLogin(
                  context,
                  redirect: '/account/reviews',
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('My reviews')),
      body: FutureBuilder<List<CustomerMyReview>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load reviews',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final rows = snap.data ?? const [];
          if (rows.isEmpty) {
            return EmptyStateView(
              title: 'No reviews yet',
              message:
                  'After a delivered order, rate products from the order or product page.',
              actionLabel: 'View orders',
              onAction: () => context.go('/orders'),
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
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, i) {
                final r = rows[i];
                return AppSurfaceCard(
                  padding: const EdgeInsets.all(AppSpace.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              r.productName ?? 'Product #${r.productId}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          AppBadge(
                            label: r.status,
                            tone: r.status == 'approved'
                                ? AppBadgeTone.success
                                : AppBadgeTone.warning,
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${r.rating}★'
                        '${r.title != null && r.title!.isNotEmpty ? ' · ${r.title}' : ''}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      if (r.body != null && r.body!.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          r.body!,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      if (r.createdAt != null) ...[
                        const SizedBox(height: 6),
                        Text(
                          formatOrderDate(r.createdAt),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      if (r.productSlug != null &&
                          r.productSlug!.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        TextButton(
                          onPressed: () =>
                              context.push('/product/${r.productSlug}'),
                          child: const Text('View product'),
                        ),
                      ],
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
