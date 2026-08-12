import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_header.dart';
import 'package:nursery_app/widgets/app_motion.dart';
import 'package:nursery_app/widgets/app_search_field.dart';
import 'package:nursery_app/widgets/mini_cart_sheet.dart';
import 'package:nursery_app/widgets/product_card.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<_HomeBundle> _future;
  final _page = PageController(viewportFraction: 0.92);
  final _bannerIndex = ValueNotifier<int>(0);

  static const _needs = [
    ('Beginner plants', '/catalog?difficulty_level=easy&product_type=plant'),
    ('Low light', '/catalog?sunlight=low&product_type=plant'),
    ('Air purifying', '/catalog?q=air%20purifying'),
    ('Balcony', '/catalog?indoor_outdoor=outdoor&product_type=plant'),
  ];

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _page.dispose();
    _bannerIndex.dispose();
    super.dispose();
  }

  Future<_HomeBundle> _load() async {
    final api = context.read<ApiClient>();
    final home = await api.getData(
      '/home',
      map: (data) => HomeFeed.fromJson(Map<String, dynamic>.from(data as Map)),
    );
    List<CategoryChip> cats = [];
    try {
      cats = await api.getData(
        '/categories',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) {
              final m = Map<String, dynamic>.from(e);
              return CategoryChip(
                name: m['name'] as String,
                slug: m['slug'] as String,
                imageUrl: m['image_url'] as String?,
                parentId: m['parent_id'] as int?,
              );
            })
            .where((c) => c.parentId == null)
            .take(10)
            .toList(),
      );
    } catch (_) {}
    return _HomeBundle(home: home, categories: cats);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: FutureBuilder<_HomeBundle>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const HomeSkeleton();
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load home',
              message: 'We couldn’t reach the nursery catalog.',
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final bundle = snap.data!;
          final home = bundle.home;
          final banners = home.banners;
          final featured = home.featuredProducts;
          final bestSellers = home.bestSellers.isNotEmpty
              ? home.bestSellers
              : featured;
          final newArrivals = home.newArrivals;
          final campaigns = home.campaigns;
          final categories = home.categories.isNotEmpty
              ? home.categories.where((c) => c.parentId == null).toList()
              : bundle.categories;

          return RefreshIndicator(
            onRefresh: () async {
              setState(() => _future = _load());
              await _future;
            },
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: FadeInUp(
                    child: AppHeader.home(
                      storeName: AppConfig.storeName,
                      deliveryLabel: 'Deliver to India',
                      onCart: () => showMiniCart(context),
                      search: AppSearchField(
                        readOnly: true,
                        onTap: () => context.push('/search'),
                      ),
                    ),
                  ),
                ),

                if (banners.isNotEmpty) ...[
                  const SliverToBoxAdapter(
                    child: SizedBox(height: AppSpace.lg),
                  ),
                  SliverToBoxAdapter(
                    child: FadeInUp(
                      delay: const Duration(milliseconds: 40),
                      child: SizedBox(
                        height: 180,
                        child: PageView.builder(
                          controller: _page,
                          itemCount: banners.length,
                          onPageChanged: (i) => _bannerIndex.value = i,
                          itemBuilder: (context, i) {
                            final b = banners[i];
                            return Padding(
                              padding: const EdgeInsets.only(
                                right: AppSpace.sm,
                              ),
                              child: Semantics(
                                button: true,
                                label: b.title,
                                child: Material(
                                  color: Colors.transparent,
                                  child: InkWell(
                                    onTap: () {
                                      final type = b.linkType;
                                      final value = b.linkValue;
                                      if (type == 'category' &&
                                          value != null &&
                                          value.isNotEmpty) {
                                        context.push(
                                          '/catalog?category=${Uri.encodeQueryComponent(value)}',
                                        );
                                      } else if (type == 'product' &&
                                          value != null &&
                                          value.isNotEmpty) {
                                        context.push('/product/$value');
                                      } else if (type == 'campaign' &&
                                          value != null &&
                                          value.isNotEmpty) {
                                        context.push('/campaigns/$value');
                                      } else if (type == 'offers') {
                                        context.push('/offers');
                                      } else if (type == 'find_plant' ||
                                          type == 'find-your-plant') {
                                        context.push('/find-your-plant');
                                      } else if (type == 'url' &&
                                          value != null &&
                                          value.startsWith('/')) {
                                        context.push(value);
                                      } else if (value != null &&
                                          value.isNotEmpty) {
                                        context.push(
                                          '/catalog?q=${Uri.encodeQueryComponent(value)}',
                                        );
                                      } else {
                                        context.push('/catalog');
                                      }
                                    },
                                    borderRadius: BorderRadius.circular(
                                      AppRadii.xl,
                                    ),
                                    child: Ink(
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(
                                          AppRadii.xl,
                                        ),
                                        child: Stack(
                                          fit: StackFit.expand,
                                          children: [
                                            CachedNetworkImage(
                                              imageUrl: b.imageUrl,
                                              fit: BoxFit.cover,
                                              memCacheWidth: 900,
                                              placeholder: (context, url) =>
                                                  Container(
                                                    color:
                                                        AppColors.surfaceMuted,
                                                  ),
                                              errorWidget:
                                                  (
                                                    context,
                                                    url,
                                                    error,
                                                  ) => Container(
                                                    color:
                                                        AppColors.primarySoft,
                                                    alignment: Alignment.center,
                                                    child: Text(
                                                      b.title,
                                                      style: Theme.of(
                                                        context,
                                                      ).textTheme.titleMedium,
                                                    ),
                                                  ),
                                            ),
                                            Container(
                                              decoration: const BoxDecoration(
                                                gradient: LinearGradient(
                                                  begin: Alignment.topCenter,
                                                  end: Alignment.bottomCenter,
                                                  colors: [
                                                    Color(0x22000000),
                                                    Color(0x99000000),
                                                  ],
                                                ),
                                              ),
                                            ),
                                            Positioned(
                                              left: AppSpace.lg,
                                              right: AppSpace.lg,
                                              bottom: AppSpace.lg,
                                              child: Text(
                                                b.title,
                                                style: Theme.of(context)
                                                    .textTheme
                                                    .headlineMedium
                                                    ?.copyWith(
                                                      color: Colors.white,
                                                    ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.only(top: AppSpace.sm),
                      child: ValueListenableBuilder<int>(
                        valueListenable: _bannerIndex,
                        builder: (context, index, _) {
                          return Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: List.generate(
                              banners.length,
                              (i) => AnimatedContainer(
                                duration: AppDuration.fast,
                                width: i == index ? 16 : 6,
                                height: 6,
                                margin: const EdgeInsets.symmetric(
                                  horizontal: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: i == index
                                      ? AppColors.primaryDeep
                                      : AppColors.borderStrong,
                                  borderRadius: BorderRadius.circular(
                                    AppRadii.full,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ),
                ],

                if (categories.isNotEmpty) ...[
                  SectionHeader(
                    title: 'Categories',
                    actionLabel: 'See all',
                    onAction: () => context.go('/categories'),
                  ).asSliver,
                  SliverToBoxAdapter(
                    child: SizedBox(
                      height: 104,
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        scrollDirection: Axis.horizontal,
                        itemCount: categories.length,
                        separatorBuilder: (context, index) =>
                            const SizedBox(width: 12),
                        itemBuilder: (context, i) {
                          final c = categories[i];
                          return InkWell(
                            onTap: () =>
                                context.push('/catalog?category=${c.slug}'),
                            borderRadius: BorderRadius.circular(AppRadii.md),
                            child: SizedBox(
                              width: 76,
                              child: Column(
                                children: [
                                  CircleAvatar(
                                    radius: 30,
                                    backgroundColor: AppColors.primarySoft,
                                    backgroundImage: c.imageUrl != null
                                        ? CachedNetworkImageProvider(
                                            c.imageUrl!,
                                          )
                                        : null,
                                    child: c.imageUrl == null
                                        ? const Icon(
                                            Icons.local_florist,
                                            color: AppColors.primaryDeep,
                                          )
                                        : null,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    c.name,
                                    maxLines: 2,
                                    textAlign: TextAlign.center,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ),
                ],

                if (bestSellers.isNotEmpty) ...[
                  SectionHeader(
                    title: 'Best sellers',
                    subtitle: 'Top-rated plants from the nursery',
                    actionLabel: 'Shop',
                    onAction: () => context.push('/catalog?sort=popular'),
                  ).asSliver,
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    sliver: SliverGrid(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 16,
                            crossAxisSpacing: 12,
                            childAspectRatio: AppLayout.productGridAspectRatio,
                          ),
                      delegate: SliverChildBuilderDelegate(
                        (context, index) =>
                            ProductCard(product: bestSellers[index]),
                        childCount: bestSellers.length.clamp(0, 8),
                      ),
                    ),
                  ),
                ],

                if (newArrivals.isNotEmpty) ...[
                  SectionHeader(
                    title: 'New arrivals',
                    subtitle: 'Fresh stock just in',
                    actionLabel: 'Shop',
                    onAction: () => context.push('/catalog?sort=newest'),
                  ).asSliver,
                  SliverToBoxAdapter(
                    child: SizedBox(
                      height: 280,
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        scrollDirection: Axis.horizontal,
                        itemCount: newArrivals.length.clamp(0, 8),
                        separatorBuilder: (context, index) =>
                            const SizedBox(width: AppSpace.md),
                        itemBuilder: (context, i) => SizedBox(
                          width: 156,
                          child: ProductCard(product: newArrivals[i]),
                        ),
                      ),
                    ),
                  ),
                ],

                if (home.recommendedForYou.isNotEmpty) ...[
                  SectionHeader(
                    title: 'Easy care picks',
                    subtitle: 'Beginner-friendly plants from the catalog',
                    actionLabel: 'Find plant',
                    onAction: () => context.push('/find-your-plant'),
                  ).asSliver,
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    sliver: SliverGrid(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 16,
                            crossAxisSpacing: 12,
                            childAspectRatio: AppLayout.productGridAspectRatio,
                          ),
                      delegate: SliverChildBuilderDelegate(
                        (context, index) => ProductCard(
                          product: home.recommendedForYou[index],
                        ),
                        childCount: home.recommendedForYou.length.clamp(0, 8),
                      ),
                    ),
                  ),
                ],

                if (home.indoorPlants.isNotEmpty) ...[
                  SectionHeader(
                    title: 'Indoor plants',
                    subtitle: 'From plant profiles marked indoor',
                  ).asSliver,
                  SliverToBoxAdapter(
                    child: SizedBox(
                      height: 280,
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        scrollDirection: Axis.horizontal,
                        itemCount: home.indoorPlants.length.clamp(0, 8),
                        separatorBuilder: (context, index) =>
                            const SizedBox(width: AppSpace.md),
                        itemBuilder: (context, i) => SizedBox(
                          width: 156,
                          child: ProductCard(product: home.indoorPlants[i]),
                        ),
                      ),
                    ),
                  ),
                ],

                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: FilledButton.tonal(
                            onPressed: () =>
                                context.push('/find-your-plant'),
                            child: const Text('Find your plant'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => context.push('/offers'),
                            child: const Text('Offers'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                SectionHeader(
                  title: 'Shop by need',
                  subtitle: 'Know your space — we’ll help you choose',
                ).asSliver,
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: 108,
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      scrollDirection: Axis.horizontal,
                      itemCount: _needs.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(width: 10),
                      itemBuilder: (context, i) {
                        final n = _needs[i];
                        return InkWell(
                          onTap: () => context.push(n.$2),
                          borderRadius: BorderRadius.circular(AppRadii.lg),
                          child: Ink(
                            width: 160,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: AppColors.primarySoft,
                              borderRadius: BorderRadius.circular(AppRadii.lg),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(
                                  Icons.spa_outlined,
                                  color: AppColors.primaryDeep,
                                ),
                                const Spacer(),
                                Text(
                                  n.$1,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryDeep,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),

                if (campaigns.isNotEmpty) ...[
                  SectionHeader(
                    title: 'Seasonal collections',
                    subtitle: 'Curated campaigns from the API',
                  ).asSliver,
                  ...campaigns.take(3).map((c) {
                    final title = c['title']?.toString() ?? 'Collection';
                    final slug = c['slug']?.toString();
                    final image = c['image_url']?.toString();
                    return SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                        child: InkWell(
                          onTap: slug == null
                              ? null
                              : () => context.push('/campaigns/$slug'),
                          borderRadius: BorderRadius.circular(AppRadii.xl),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(AppRadii.xl),
                            child: SizedBox(
                              height: 140,
                              child: Stack(
                                fit: StackFit.expand,
                                children: [
                                  if (image != null)
                                    CachedNetworkImage(
                                      imageUrl: image,
                                      fit: BoxFit.cover,
                                    )
                                  else
                                    Container(color: AppColors.primaryDeep),
                                  Container(color: Colors.black38),
                                  Positioned(
                                    left: 16,
                                    bottom: 16,
                                    right: 16,
                                    child: Text(
                                      title,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 20,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                ],

                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(AppRadii.lg),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: const Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Why shop with us',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                              color: AppColors.primaryDeep,
                            ),
                          ),
                          SizedBox(height: 10),
                          _TrustRow(
                            title: 'Healthy plants',
                            body: 'Packed carefully for delivery',
                          ),
                          _TrustRow(
                            title: 'Clear plant care',
                            body: 'Sun, water & difficulty at a glance',
                          ),
                          _TrustRow(
                            title: 'Secure checkout',
                            body: 'COD and online options via account',
                          ),
                        ],
                      ),
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

class _HomeBundle {
  _HomeBundle({required this.home, required this.categories});

  final HomeFeed home;
  final List<CategoryChip> categories;
}

class _TrustRow extends StatelessWidget {
  const _TrustRow({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpace.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.check_circle_rounded,
            size: 18,
            color: AppColors.success,
          ),
          const SizedBox(width: AppSpace.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(
                  body,
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

extension on Widget {
  Widget get asSliver => SliverToBoxAdapter(child: this);
}
