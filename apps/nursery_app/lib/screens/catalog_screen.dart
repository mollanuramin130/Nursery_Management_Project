import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/providers/catalog_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_search_field.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';
import 'package:nursery_app/widgets/product_card.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class CatalogScreen extends StatelessWidget {
  const CatalogScreen({
    super.key,
    this.initialFilters = const CatalogFilters(),
    this.focusSearch = false,
  });

  final CatalogFilters initialFilters;
  final bool focusSearch;

  @override
  Widget build(BuildContext context) {
    // QA-41: do not remount CatalogProvider on filter/query changes —
    // remount cleared items and flashed ProductGridSkeleton (QA-40-M-009).
    return ChangeNotifierProvider(
      create: (ctx) => CatalogProvider(ctx.read<CatalogRepository>())
        ..applyFilters(initialFilters),
      child: _CatalogView(
        initialFilters: initialFilters,
        focusSearch: focusSearch,
      ),
    );
  }
}

class _CatalogView extends StatefulWidget {
  const _CatalogView({required this.initialFilters, required this.focusSearch});

  final CatalogFilters initialFilters;
  final bool focusSearch;

  @override
  State<_CatalogView> createState() => _CatalogViewState();
}

class _CatalogViewState extends State<_CatalogView> {
  late final TextEditingController _search;
  final _focus = FocusNode();
  final _scroll = ScrollController();
  int _lastSyncGen = -1;

  @override
  void initState() {
    super.initState();
    _search = TextEditingController(text: widget.initialFilters.q ?? '');
    _scroll.addListener(_onScroll);
    if (widget.focusSearch) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _focus.requestFocus();
      });
    }
  }

  @override
  void didUpdateWidget(covariant _CatalogView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialFilters.toRouteQuery().toString() !=
        widget.initialFilters.toRouteQuery().toString()) {
      _search.text = widget.initialFilters.q ?? '';
      context.read<CatalogProvider>().applyFilters(widget.initialFilters);
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final gen = context.watch<OfflineController>().syncGeneration;
    if (_lastSyncGen >= 0 && gen != _lastSyncGen) {
      _lastSyncGen = gen;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        final catalog = context.read<CatalogProvider>();
        if (catalog.items.isNotEmpty) {
          catalog.load(reset: true);
        }
      });
    } else {
      _lastSyncGen = gen;
    }
  }

  @override
  void dispose() {
    _search.dispose();
    _focus.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    final catalog = context.read<CatalogProvider>();
    if (!catalog.hasMore || catalog.loadingMore || catalog.loading) return;
    if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 420) {
      catalog.load(reset: false);
    }
  }

  void _syncRoute(CatalogFilters next) {
    final params = next.toRouteQuery();
    // Keep listing on /catalog so SearchScreen stays a dedicated suggest UX.
    final uri = Uri(
      path: '/catalog',
      queryParameters: params.isEmpty ? null : params,
    );
    context.go(uri.toString());
  }

  Future<void> _applyFilters(CatalogFilters next) async {
    // Route rebuild recreates CatalogProvider (ValueKey) from query params.
    _syncRoute(next);
  }

  void _submitSearch() {
    final catalog = context.read<CatalogProvider>();
    final q = _search.text.trim();
    _applyFilters(catalog.filters.copyWith(q: q, clearQ: q.isEmpty));
  }

  String _title(CatalogFilters filters) {
    if (filters.category != null) {
      return filters.category!.replaceAll('-', ' ');
    }
    if (filters.q != null && filters.q!.isNotEmpty) return 'Search';
    return 'Shop';
  }

  @override
  Widget build(BuildContext context) {
    final catalog = context.watch<CatalogProvider>();
    final filters = catalog.filters;
    final title = _title(filters);
    final titled = title[0].toUpperCase() + title.substring(1);

    return Scaffold(
      appBar: AppBar(
        title: Text(titled),
        leading: const GreenLeafBackButton(),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.sm,
              AppSpace.screen,
              AppSpace.sm,
            ),
            child: AppSearchField(
              controller: _search,
              focusNode: _focus,
              onSubmitted: (_) => _submitSearch(),
              onClear: () {
                _search.clear();
                _submitSearch();
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpace.screen),
            child: Row(
              children: [
                OutlinedButton.icon(
                  onPressed: () async {
                    final next = await showCatalogFilterSheet(context, filters);
                    if (next != null && mounted) await _applyFilters(next);
                  },
                  icon: Badge(
                    isLabelVisible: filters.hasActiveFilters,
                    label: Text('${filters.activeFilterCount}'),
                    child: const Icon(Icons.tune_rounded, size: 18),
                  ),
                  label: const Text('Filter'),
                ),
                const SizedBox(width: AppSpace.sm),
                OutlinedButton.icon(
                  onPressed: () async {
                    final sort = await showCatalogSortSheet(
                      context,
                      filters.sort,
                    );
                    if (sort != null && mounted) {
                      await _applyFilters(filters.copyWith(sort: sort));
                    }
                  },
                  icon: const Icon(Icons.sort_rounded, size: 18),
                  label: const Text('Sort'),
                ),
                const Spacer(),
                if (catalog.total != null)
                  Text(
                    '${catalog.total}',
                    style: Theme.of(context).textTheme.bodySmall,
                  )
                else if (!catalog.loading)
                  Text(
                    '${catalog.items.length}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            ),
          ),
          if (filters.hasActiveFilters)
            SizedBox(
              height: 44,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(
                  AppSpace.screen,
                  AppSpace.sm,
                  AppSpace.screen,
                  0,
                ),
                children: [
                  ..._activeChips(filters),
                  TextButton(
                    onPressed: () => _applyFilters(filters.clearedFilters()),
                    child: const Text('Clear'),
                  ),
                ],
              ),
            ),
          const SizedBox(height: AppSpace.sm),
          Expanded(child: _body(catalog)),
        ],
      ),
    );
  }

  List<Widget> _activeChips(CatalogFilters filters) {
    final chips = <Widget>[];
    void add(String label, VoidCallback onClear) {
      chips.add(
        Padding(
          padding: const EdgeInsets.only(right: AppSpace.sm),
          child: InputChip(
            label: Text(label),
            onDeleted: onClear,
            backgroundColor: AppColors.primarySoft,
            deleteIconColor: AppColors.primaryDeep,
          ),
        ),
      );
    }

    if (filters.productType != null) {
      add(
        filters.productType!,
        () => _applyFilters(filters.copyWith(clearProductType: true)),
      );
    }
    if (filters.indoorOutdoor != null) {
      add(
        filters.indoorOutdoor!,
        () => _applyFilters(filters.copyWith(clearIndoorOutdoor: true)),
      );
    }
    if (filters.sunlight != null) {
      add(
        filters.sunlight!,
        () => _applyFilters(filters.copyWith(clearSunlight: true)),
      );
    }
    if (filters.waterRequirement != null) {
      add(
        filters.waterRequirement!,
        () => _applyFilters(filters.copyWith(clearWater: true)),
      );
    }
    if (filters.difficultyLevel != null) {
      add(
        filters.difficultyLevel!,
        () => _applyFilters(filters.copyWith(clearDifficulty: true)),
      );
    }
    if (filters.availability != null) {
      add(
        filters.availability!,
        () => _applyFilters(filters.copyWith(clearAvailability: true)),
      );
    }
    if (filters.minPrice != null || filters.maxPrice != null) {
      add(
        '₹${filters.minPrice ?? '0'}–${filters.maxPrice ?? '∞'}',
        () => _applyFilters(
          filters.copyWith(clearMinPrice: true, clearMaxPrice: true),
        ),
      );
    }
    return chips;
  }

  Widget _body(CatalogProvider catalog) {
    if (catalog.loading && catalog.items.isEmpty) {
      return const ProductGridSkeleton(count: 6);
    }
    if (catalog.error != null && catalog.items.isEmpty) {
      return ErrorStateView(
        title: 'Unable to load plants',
        message: ErrorStateView.sanitize(catalog.error),
        onRetry: () => catalog.load(reset: true),
      );
    }
    if (catalog.items.isEmpty) {
      return EmptyStateView(
        title: 'No plants found',
        message: 'Try clearing filters or searching something else.',
        actionLabel: 'Clear filters',
        onAction: () {
          _search.clear();
          _applyFilters(const CatalogFilters());
        },
        secondaryActionLabel: 'Browse categories',
        onSecondaryAction: () => context.go('/categories'),
      );
    }

    return RefreshIndicator(
      onRefresh: () => catalog.load(reset: true),
      child: CustomScrollView(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.xs,
              AppSpace.screen,
              AppSpace.xxl,
            ),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: AppSpace.lg,
                crossAxisSpacing: AppSpace.md,
                childAspectRatio: AppLayout.productGridAspectRatio,
              ),
              delegate: SliverChildBuilderDelegate(
                (context, i) => ProductCard(product: catalog.items[i]),
                childCount: catalog.items.length,
              ),
            ),
          ),
          if (catalog.loadingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: AppSpace.xxl),
                child: Center(child: CircularProgressIndicator()),
              ),
            ),
        ],
      ),
    );
  }
}
