import 'package:flutter/material.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/product_card.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class CampaignScreen extends StatefulWidget {
  const CampaignScreen({super.key, required this.slug});

  final String slug;

  @override
  State<CampaignScreen> createState() => _CampaignScreenState();
}

class _CampaignScreenState extends State<CampaignScreen> {
  late Future<_CampaignBundle> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_CampaignBundle> _load() {
    return context.read<ApiClient>().getData(
      '/campaigns/${widget.slug}',
      map: (data) {
        final map = Map<String, dynamic>.from(data as Map);
        final products = ((map['products'] as List?) ?? [])
            .whereType<Map>()
            .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        return _CampaignBundle(
          title: map['title']?.toString() ?? 'Collection',
          subtitle: map['subtitle']?.toString(),
          description: map['description']?.toString(),
          imageUrl: (map['banner_image'] ?? map['image_url'])?.toString(),
          status: map['status']?.toString(),
          products: products,
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Collection'),
        leading: const GreenLeafBackButton(),
      ),
      body: FutureBuilder<_CampaignBundle>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const ProductGridSkeleton(count: 4);
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load collection',
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
          final data = snap.data!;
          if (data.products.isEmpty) {
            return EmptyStateView(
              title: 'No plants in this collection',
              message: 'Browse the shop for more seasonal picks.',
              actionLabel: 'Shop all',
              onAction: () => context.go('/catalog'),
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
              );
            },
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpace.screen,
                      AppSpace.sm,
                      AppSpace.screen,
                      AppSpace.lg,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (data.imageUrl != null && data.imageUrl!.isNotEmpty)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(AppRadii.lg),
                            child: AspectRatio(
                              aspectRatio: 16 / 9,
                              child: ResilientNetworkImage(
                                url: data.imageUrl,
                                fit: BoxFit.cover,
                              ),
                            ),
                          ),
                        if (data.imageUrl != null) const SizedBox(height: 12),
                        if (data.status != null)
                          AppBadge(
                            label: data.status!.replaceAll('_', ' '),
                            tone: data.status == 'active'
                                ? AppBadgeTone.success
                                : AppBadgeTone.neutral,
                          ),
                        const SizedBox(height: 8),
                        Text(
                          data.title,
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(
                                color: AppColors.primaryDeep,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        if (data.subtitle != null && data.subtitle!.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: AppSpace.xs),
                            child: Text(
                              data.subtitle!,
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: AppColors.inkSoft),
                            ),
                          ),
                        if (data.description != null &&
                            data.description!.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: AppSpace.sm),
                            child: Text(data.description!),
                          ),
                        Padding(
                          padding: const EdgeInsets.only(top: AppSpace.sm),
                          child: Text(
                            '${data.products.length} products',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpace.screen,
                    0,
                    AppSpace.screen,
                    AppSpace.xxl,
                  ),
                  sliver: SliverGrid(
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      mainAxisSpacing: AppSpace.lg,
                      crossAxisSpacing: AppSpace.md,
                      childAspectRatio: AppLayout.productGridAspectRatio,
                    ),
                    delegate: SliverChildBuilderDelegate(
                      (context, i) => ProductCard(product: data.products[i]),
                      childCount: data.products.length,
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _CampaignBundle {
  _CampaignBundle({
    required this.title,
    required this.subtitle,
    required this.products,
    this.description,
    this.imageUrl,
    this.status,
  });

  final String title;
  final String? subtitle;
  final String? description;
  final String? imageUrl;
  final String? status;
  final List<ProductSummary> products;
}
