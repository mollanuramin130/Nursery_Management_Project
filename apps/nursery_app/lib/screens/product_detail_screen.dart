import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/services/recently_viewed_store.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/app_motion.dart';
import 'package:nursery_app/widgets/product_card.dart';
import 'package:nursery_app/widgets/product_reviews_section.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/sticky_commerce_bar.dart';
import 'package:nursery_app/widgets/subscribe_section.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({
    super.key,
    required this.slug,
    this.openReviewForm = false,
  });

  final String slug;
  final bool openReviewForm;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late Future<ProductDetail> _future;
  PlantCareGuide? _care;
  List<ProductSummary> _related = const [];
  List<ProductSummary> _recommended = const [];
  List<ProductSummary> _recent = const [];
  int qty = 1;
  int _imageIndex = 0;
  bool _busy = false;
  bool _wishBusy = false;

  @override
  void initState() {
    super.initState();
    _future = _loadProduct();
  }

  Future<ProductDetail> _loadProduct() async {
    final api = context.read<ApiClient>();
    final repo = context.read<CatalogRepository>();
    final resolved = await repo.getProduct(widget.slug);
    final product = resolved.data;
    try {
      _care = await api.getData(
        '/plants/${widget.slug}/care',
        map: (data) {
          if (data is! Map || data.isEmpty) return null;
          return PlantCareGuide.fromJson(Map<String, dynamic>.from(data));
        },
      );
    } catch (_) {
      // Offline: use plant profile from product detail as care substitute.
      _care = null;
    }
    try {
      final related = await repo.related(product.summary.id);
      _related = related.data;
    } catch (_) {
      _related = const [];
    }
    try {
      _recommended = await api.getData(
        '/products/${product.summary.id}/recommendations',
        query: {'type': 'similar'},
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    } catch (_) {
      _recommended = _related;
    }
    await RecentlyViewedStore.track(
      RecentProduct(
        id: product.summary.id,
        slug: product.summary.slug,
        name: product.summary.name,
        thumbnailUrl: product.summary.thumbnailUrl,
        price: product.summary.price,
      ),
    );
    // Best-effort server view event (auth or guest).
    try {
      await api.sendData(
        'POST',
        '/product-views',
        body: {'product_id': product.summary.id},
        map: (_) => true,
      );
    } catch (_) {}
    final recentRows = await RecentlyViewedStore.list(
      excludeId: product.summary.id,
    );
    _recent = recentRows
        .take(8)
        .map(
          (p) => ProductSummary(
            id: p.id,
            slug: p.slug,
            name: p.name,
            price: p.price ?? 0,
            thumbnailUrl: p.thumbnailUrl,
            stockStatus: 'in_stock',
            productType: 'plant',
          ),
        )
        .toList();
    return product;
  }

  Future<void> _addToCart(ProductDetail product, {bool buyNow = false}) async {
    if (_busy || product.summary.isOutOfStock) return;
    setState(() => _busy = true);
    try {
      await context.read<CartProvider>().addItem(
        product.summary.id,
        quantity: qty,
      );
      if (!mounted) return;
      if (buyNow) {
        final signedIn = context.read<AuthProvider>().user != null;
        if (!signedIn) {
          AuthNavigation.pushLogin(context, redirect: '/checkout');
        } else {
          context.push('/checkout');
        }
      } else {
        AppFeedback.success(
          context,
          'Added to cart',
          actionLabel: 'Open',
          onAction: () {
            if (context.mounted) context.go('/cart');
          },
        );
      }
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(context, 'Unable to add item. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _toggleWish(int productId) async {
    final auth = context.read<AuthProvider>();
    if (auth.user == null) {
      AuthNavigation.pushLogin(context, redirect: '/product/${widget.slug}');
      return;
    }
    // QA-37-003: ignore rapid double-taps on PDP heart.
    if (_wishBusy) return;
    setState(() => _wishBusy = true);
    try {
      final saved = await context.read<WishlistProvider>().toggle(productId);
      if (!mounted) return;
      AppFeedback.success(
        context,
        saved ? 'Added to wishlist' : 'Removed from wishlist',
      );
    } catch (_) {
      if (!mounted) return;
      AppFeedback.error(
        context,
        'Unable to update wishlist. Please try again.',
      );
    } finally {
      if (mounted) setState(() => _wishBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<ProductDetail>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Scaffold(body: ProductDetailSkeleton());
        }
        if (snap.hasError || !snap.hasData) {
          return Scaffold(
            appBar: AppBar(
              leading: const GreenLeafBackButton(),
            ),
            body: ErrorStateView(
              title: 'Unable to load plant',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () {
                softReplaceFuture(
                  load: _loadProduct,
                  onData: (data) {
                    if (!mounted) return;
                    setState(() => _future = completedFuture(data));
                  },
                );
              },
            ),
          );
        }

        final product = snap.data!;
        final images = product.images.isNotEmpty
            ? product.images
            : [
                if (product.summary.thumbnailUrl != null)
                  product.summary.thumbnailUrl!,
              ];
        final plant = product.plant;
        final out = product.summary.isOutOfStock;
        final bottom = MediaQuery.paddingOf(context).bottom;
        final wishSaved = context.watch<WishlistProvider>().contains(
          product.summary.id,
        );

        return Scaffold(
          appBar: AppBar(
            title: Text(product.summary.name),
            leading: const GreenLeafBackButton(),
            actions: [
              WishlistHeart(
                saved: wishSaved,
                busy: _wishBusy,
                productName: product.summary.name,
                onPressed: () => _toggleWish(product.summary.id),
              ),
            ],
          ),
          body: RefreshIndicator(
            onRefresh: () async {
              await softReplaceFuture(
                load: _loadProduct,
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
            child: ListView(
            padding: EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.sm,
              AppSpace.screen,
              120 + bottom,
            ),
            children: [
              AspectRatio(
                aspectRatio: 4 / 5,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadii.xl),
                  child: PageView.builder(
                    itemCount: images.isEmpty ? 1 : images.length,
                    onPageChanged: (i) => setState(() => _imageIndex = i),
                    itemBuilder: (_, i) {
                      if (images.isEmpty) {
                        return Container(
                          color: AppColors.surfaceMuted,
                          alignment: Alignment.center,
                          child: const Icon(
                            Icons.local_florist_outlined,
                            size: 48,
                            color: AppColors.muted,
                          ),
                        );
                      }
                      return ResilientNetworkImage(
                        url: images[i],
                        fit: BoxFit.cover,
                      );
                    },
                  ),
                ),
              ),
              if (images.length > 1) ...[
                const SizedBox(height: 10),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    images.length,
                    (i) => Container(
                      width: i == _imageIndex ? 16 : 6,
                      height: 6,
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      decoration: BoxDecoration(
                        color: i == _imageIndex
                            ? AppColors.primaryDeep
                            : AppColors.borderStrong,
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              if (product.summary.productType != null)
                Text(
                  product.summary.productType!.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1,
                    color: AppColors.muted,
                  ),
                ),
              Text(
                product.summary.name,
                style: Theme.of(context).textTheme.displaySmall,
              ),
              if (plant?.scientificName != null ||
                  product.summary.scientificName != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    plant?.scientificName ?? product.summary.scientificName!,
                    style: const TextStyle(
                      fontStyle: FontStyle.italic,
                      color: AppColors.muted,
                    ),
                  ),
                ),
              if (product.sku != null && product.sku!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    'SKU ${product.sku}',
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: AppColors.muted),
                  ),
                ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  RatingRow(
                    avg: product.summary.ratingAvg,
                    count: product.summary.ratingCount,
                  ),
                  AppBadge(
                    label: out
                        ? 'Out of stock'
                        : product.summary.isLowStock
                        ? 'Low stock'
                        : 'In stock',
                    tone: out
                        ? AppBadgeTone.error
                        : product.summary.isLowStock
                        ? AppBadgeTone.warning
                        : AppBadgeTone.success,
                  ),
                  ...product.summary.badges
                      .take(2)
                      .map(
                        (b) => AppBadge(
                          label: b.replaceAll('-', ' '),
                          tone: b == 'sale'
                              ? AppBadgeTone.sale
                              : b == 'low-stock'
                              ? AppBadgeTone.warning
                              : AppBadgeTone.brand,
                        ),
                      ),
                ],
              ),
              if (!out &&
                  product.availableQty != null &&
                  product.availableQty! > 0 &&
                  product.availableQty! <= 8)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    '${product.availableQty} available',
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: AppColors.warning,
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              PriceText(
                price: product.summary.price,
                compareAt: product.summary.compareAtPrice,
                currency: product.summary.currency,
                large: true,
              ),
              if (product.description != null) ...[
                const SizedBox(height: 16),
                Text(
                  product.description!,
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
              ],
              if (out) ...[
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Out of stock',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Get notified in the app when this product is available again.',
                        ),
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: () async {
                            final auth = context.read<AuthProvider>();
                            if (auth.user == null) {
                              AuthNavigation.pushLogin(
                                context,
                                redirect: '/product/${product.summary.slug}',
                              );
                              return;
                            }
                            try {
                              await context.read<ApiClient>().sendData(
                                'POST',
                                '/products/${product.summary.id}/stock-alert',
                                map: (_) => true,
                              );
                              if (!mounted) return;
                              AppFeedback.success(
                                context,
                                'We will notify you when this is back in stock',
                              );
                            } on ApiException catch (e) {
                              if (!mounted) return;
                              AppFeedback.error(context, e.message);
                            } catch (_) {
                              if (!mounted) return;
                              AppFeedback.error(
                                context,
                                'Unable to create stock alert.',
                              );
                            }
                          },
                          child: const Text('Notify me'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 20),
              Row(
                children: [
                  const Text(
                    'Qty',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(width: AppSpace.md),
                  QtySelector(
                    value: qty,
                    onChanged: (v) => setState(() => qty = v),
                  ),
                ],
              ),
              if (plant != null || _care != null) ...[
                const SizedBox(height: 28),
                Text(
                  'Plant information',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _InfoChip(
                      label: 'Sunlight',
                      value: _care?.sunlightLabel ?? plant?.sunlight,
                    ),
                    _InfoChip(
                      label: 'Water',
                      value: _care?.waterLabel ?? plant?.waterRequirement,
                    ),
                    _InfoChip(
                      label: 'Soil',
                      value: _care?.soilType ?? plant?.soilType,
                    ),
                    _InfoChip(label: 'Placement', value: plant?.indoorOutdoor),
                    _InfoChip(
                      label: 'Difficulty',
                      value: _care?.difficultyLevel ?? plant?.difficultyLevel,
                    ),
                    _InfoChip(
                      label: 'Pet safety',
                      value: _care?.petSafety ?? plant?.petSafety,
                    ),
                    if (_care?.temperatureMinC != null &&
                        _care?.temperatureMaxC != null)
                      _InfoChip(
                        label: 'Temperature',
                        value:
                            '${_care!.temperatureMinC!.round()}–${_care!.temperatureMaxC!.round()}°C',
                      ),
                  ],
                ),
              ],
              if (_care != null) ...[
                const SizedBox(height: 24),
                Text(
                  'Care guide',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 8),
                ..._careSections(_care!),
              ] else if (plant?.growingInstructions != null) ...[
                const SizedBox(height: 16),
                Text(plant!.growingInstructions!),
              ],
              const SizedBox(height: 28),
              SubscribeSection(product: product),
              const SizedBox(height: 28),
              ProductReviewsSection(
                productId: product.summary.id,
                productSlug: widget.slug,
                openForm: widget.openReviewForm,
              ),
              if (_related.isNotEmpty) ...[
                const SizedBox(height: 28),
                Text(
                  'You may also like',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 280,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: _related.length.clamp(0, 8),
                    separatorBuilder: (context, index) =>
                        const SizedBox(width: AppSpace.md),
                    itemBuilder: (context, i) {
                      return SizedBox(
                        width: 156,
                        child: ProductCard(product: _related[i]),
                      );
                    },
                  ),
                ),
              ],
              if (_recommended.isNotEmpty) ...[
                const SizedBox(height: 28),
                Text(
                  'Complete your garden',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 280,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: _recommended.length.clamp(0, 8),
                    separatorBuilder: (context, index) =>
                        const SizedBox(width: AppSpace.md),
                    itemBuilder: (context, i) {
                      return SizedBox(
                        width: 156,
                        child: ProductCard(product: _recommended[i]),
                      );
                    },
                  ),
                ),
              ],
              if (_recent.isNotEmpty) ...[
                const SizedBox(height: 28),
                Text(
                  'Recently viewed',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 280,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: _recent.length.clamp(0, 8),
                    separatorBuilder: (context, index) =>
                        const SizedBox(width: AppSpace.md),
                    itemBuilder: (context, i) {
                      return SizedBox(
                        width: 156,
                        child: ProductCard(product: _recent[i]),
                      );
                    },
                  ),
                ),
              ],
            ],
          ),
          ),
          bottomNavigationBar: StickyCommerceBar(
            priceLabel: 'Total',
            price: money(product.summary.price * qty),
            secondaryLabel: 'Add',
            onSecondary: out || _busy ? null : () => _addToCart(product),
            primaryLabel: out ? 'Unavailable' : 'Buy now',
            onPrimary: out || _busy
                ? null
                : () => _addToCart(product, buyNow: true),
            loading: _busy,
            enabled: !out,
          ),
        );
      },
    );
  }

  List<Widget> _careSections(PlantCareGuide care) {
    final sections = <(String, String?)>[
      ('Growing', care.growingInstructions),
      ('Planting', care.plantingInstructions),
      ('Fertilization', care.fertilizationInstructions),
      ('Pruning', care.pruningInstructions),
      ('Pests & diseases', care.pestDiseaseInfo),
      ('Toxicity', care.toxicityInfo),
    ];
    return sections
        .where((s) => s.$2 != null && s.$2!.trim().isNotEmpty)
        .map(
          (s) => Theme(
            data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
            child: ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                s.$1,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(s.$2!),
                  ),
                ),
              ],
            ),
          ),
        )
        .toList();
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.label, this.value});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    if (value == null || value!.isEmpty) return const SizedBox.shrink();
    return Container(
      width: (MediaQuery.sizeOf(context).width - 40) / 2,
      padding: const EdgeInsets.all(AppSpace.md),
      decoration: BoxDecoration(
        color: AppColors.primarySoft,
        borderRadius: BorderRadius.circular(AppRadii.md),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: AppColors.muted,
            ),
          ),
          const SizedBox(height: AppSpace.xs),
          Text(
            value!.replaceAll('_', ' '),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.primaryDeep,
            ),
          ),
        ],
      ),
    );
  }
}
