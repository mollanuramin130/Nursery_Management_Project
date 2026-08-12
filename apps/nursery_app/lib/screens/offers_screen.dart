import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/product_card.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class OffersScreen extends StatefulWidget {
  const OffersScreen({super.key});

  @override
  State<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends State<OffersScreen> {
  late Future<_OffersFeed> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_OffersFeed> _load() {
    return context.read<ApiClient>().getData(
      '/offers',
      query: {'per_page': 16},
      map: (data) {
        final map = Map<String, dynamic>.from(data as Map);
        List<Map<String, dynamic>> maps(String key) =>
            ((map[key] as List?) ?? [])
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList();
        final sales = ((map['sale_products'] as List?) ?? [])
            .whereType<Map>()
            .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        return _OffersFeed(
          featured: maps('featured_campaigns'),
          upcoming: maps('upcoming_campaigns'),
          sales: sales,
          coupons: maps('public_coupons'),
        );
      },
    );
  }

  String _ends(Map<String, dynamic> c) {
    final raw = c['ends_at']?.toString();
    if (raw == null || raw.isEmpty) return '';
    return formatOrderDate(raw);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Offers')),
      body: FutureBuilder<_OffersFeed>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const ProductGridSkeleton(count: 4);
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load offers',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final feed = snap.data!;
          final hero = feed.featured.isNotEmpty ? feed.featured.first : null;

          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                Text(
                  'Grow more, spend less',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 4),
                Text(
                  'Live campaigns and sale prices from the nursery.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ActionChip(
                      label: const Text('Find your plant'),
                      onPressed: () => context.push('/find-your-plant'),
                    ),
                    ActionChip(
                      label: const Text('Sale items'),
                      onPressed: () => context.push('/catalog?on_sale=1'),
                    ),
                  ],
                ),
                if (hero != null) ...[
                  const SizedBox(height: 16),
                  _CampaignHero(
                    campaign: hero,
                    endsLabel: _ends(hero),
                    onTap: () =>
                        context.push('/campaigns/${hero['slug']}'),
                  ),
                ],
                if (feed.featured.length > 1) ...[
                  const SizedBox(height: 24),
                  Text(
                    'Seasonal campaigns',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 160,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: feed.featured.length - 1,
                      separatorBuilder: (_, _) => const SizedBox(width: 10),
                      itemBuilder: (_, i) {
                        final c = feed.featured[i + 1];
                        return SizedBox(
                          width: 220,
                          child: _CampaignCard(
                            campaign: c,
                            onTap: () =>
                                context.push('/campaigns/${c['slug']}'),
                          ),
                        );
                      },
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                Text(
                  'On sale',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 10),
                if (feed.sales.isEmpty)
                  const Text('No sale items right now.')
                else
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: feed.sales.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      childAspectRatio: 0.68,
                    ),
                    itemBuilder: (_, i) =>
                        ProductCard(product: feed.sales[i]),
                  ),
                if (feed.upcoming.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text(
                    'Coming soon',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 10),
                  ...feed.upcoming.map(
                    (c) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(c['title']?.toString() ?? 'Campaign'),
                      subtitle: Text(
                        'Starts ${formatOrderDate(c['starts_at']?.toString())}',
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () =>
                          context.push('/campaigns/${c['slug']}'),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _OffersFeed {
  _OffersFeed({
    required this.featured,
    required this.upcoming,
    required this.sales,
    required this.coupons,
  });

  final List<Map<String, dynamic>> featured;
  final List<Map<String, dynamic>> upcoming;
  final List<ProductSummary> sales;
  final List<Map<String, dynamic>> coupons;
}

class _CampaignHero extends StatelessWidget {
  const _CampaignHero({
    required this.campaign,
    required this.onTap,
    this.endsLabel = '',
  });

  final Map<String, dynamic> campaign;
  final VoidCallback onTap;
  final String endsLabel;

  @override
  Widget build(BuildContext context) {
    final image =
        (campaign['banner_image'] ?? campaign['image_url'])?.toString();
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.lg),
        child: SizedBox(
          height: 200,
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (image != null && image.isNotEmpty)
                CachedNetworkImage(imageUrl: image, fit: BoxFit.cover)
              else
                Container(color: AppColors.primaryDeep),
              Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Colors.black87],
                  ),
                ),
              ),
              Positioned(
                left: 16,
                right: 16,
                bottom: 16,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      campaign['title']?.toString() ?? 'Collection',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 22,
                      ),
                    ),
                    if ((campaign['subtitle'] ?? '').toString().isNotEmpty)
                      Text(
                        campaign['subtitle'].toString(),
                        style: const TextStyle(color: Colors.white70),
                      ),
                    if (endsLabel.isNotEmpty)
                      Text(
                        'Valid until $endsLabel',
                        style: const TextStyle(
                          color: Colors.white60,
                          fontSize: 12,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CampaignCard extends StatelessWidget {
  const _CampaignCard({required this.campaign, required this.onTap});

  final Map<String, dynamic> campaign;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final image =
        (campaign['image_url'] ?? campaign['banner_image'])?.toString();
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (image != null && image.isNotEmpty)
              CachedNetworkImage(imageUrl: image, fit: BoxFit.cover)
            else
              Container(color: AppColors.primaryDeep),
            Container(color: Colors.black45),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Align(
                alignment: Alignment.bottomLeft,
                child: Text(
                  campaign['title']?.toString() ?? '',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
