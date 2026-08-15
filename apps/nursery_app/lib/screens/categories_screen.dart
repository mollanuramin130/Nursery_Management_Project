import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/refresh_coalescer.dart';
import 'package:nursery_app/data/mock_asset_store.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/app_bottom_sheet.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class CategoryItem {
  CategoryItem({
    required this.id,
    required this.name,
    required this.slug,
    this.imageUrl,
    this.children = const [],
  });

  final int id;
  final String name;
  final String slug;
  final String? imageUrl;
  final List<CategoryItem> children;

  factory CategoryItem.fromJson(Map<String, dynamic> json) => CategoryItem(
    id: json['id'] as int,
    name: json['name'] as String,
    slug: json['slug'] as String,
    imageUrl: (json['image_url'] ?? json['image'] ?? json['thumbnail_url'])
        as String?,
    children: ((json['children'] as List?) ?? [])
        .whereType<Map>()
        .map((e) => CategoryItem.fromJson(Map<String, dynamic>.from(e)))
        .toList(),
  );
}

class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key});

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  List<CategoryItem>? _cats;
  Object? _error;
  bool _loading = true;
  bool _softUpdating = false;
  int _epoch = 0;
  int _lastSyncGen = -1;
  final _refresh = RefreshCoalescer();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final gen = context.watch<OfflineController>().syncGeneration;
    if (_lastSyncGen >= 0 && gen != _lastSyncGen && _cats != null) {
      _lastSyncGen = gen;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _reload();
      });
    } else {
      _lastSyncGen = gen;
    }
  }

  Future<void> _reload() => _refresh.run(_reloadBody);

  Future<void> _reloadBody() async {
    final epoch = ++_epoch;
    final hasData = _cats != null;
    setState(() {
      if (hasData) {
        _softUpdating = true;
      } else {
        _loading = true;
      }
      _error = null;
    });
    try {
      final cats = await _load();
      if (!mounted || epoch != _epoch) return;
      setState(() {
        _cats = cats;
        _loading = false;
        _softUpdating = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted || epoch != _epoch) return;
      setState(() {
        _loading = false;
        _softUpdating = false;
        if (_cats == null) _error = e;
      });
    }
  }

  Future<List<CategoryItem>> _load() async {
    try {
      return await context.read<ApiClient>().getData(
        '/categories',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => CategoryItem.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    } catch (_) {
      final data = await MockAssetStore().dataOf('categories.json');
      return (data as List)
          .whereType<Map>()
          .map((e) => CategoryItem.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Categories')),
      body: _loading && _cats == null
          ? const CategoryGridSkeleton()
          : _error != null && _cats == null
              ? ErrorStateView(
                  title: 'Unable to load categories',
                  message: ErrorStateView.sanitize(_error?.toString()),
                  onRetry: _reload,
                )
              : (_cats == null || _cats!.isEmpty)
                  ? EmptyStateView(
                      title: 'No categories yet',
                      message: 'Browse the full shop while we grow this list.',
                      actionLabel: 'Shop all',
                      onAction: () => context.push('/catalog'),
                      icon: Icons.grid_view_rounded,
                    )
                  : RefreshIndicator(
                      onRefresh: _reload,
                      child: _buildGrid(context, _cats!),
                    ),
    );
  }

  Widget _buildGrid(BuildContext context, List<CategoryItem> cats) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.sm,
              AppSpace.screen,
              AppSpace.xxl,
            ),
            children: [
              Text(
                'Shop by category',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: AppSpace.xs),
              Text(
                'Find plants and essentials for every corner.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: AppSpace.lg),
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: cats.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: AppSpace.md,
                  crossAxisSpacing: AppSpace.md,
                  childAspectRatio: 0.95,
                ),
                itemBuilder: (context, i) {
                  final c = cats[i];
                  return InkWell(
                    onTap: () {
                      if (c.children.isEmpty) {
                        context.push('/catalog?category=${c.slug}');
                        return;
                      }
                      AppBottomSheet.show<void>(
                        context: context,
                        title: c.name,
                        child: Column(
                          children: [
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: const Text('Shop all in this category'),
                              trailing: const Icon(Icons.chevron_right_rounded),
                              onTap: () {
                                Navigator.pop(context);
                                context.push('/catalog?category=${c.slug}');
                              },
                            ),
                            const Divider(),
                            ...c.children.map(
                              (child) => ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(child.name),
                                trailing: const Icon(
                                  Icons.chevron_right_rounded,
                                ),
                                onTap: () {
                                  Navigator.pop(context);
                                  context.push(
                                    '/catalog?category=${child.slug}',
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(AppRadii.lg),
                    child: Ink(
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(AppRadii.lg),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child: ClipRRect(
                              borderRadius: const BorderRadius.vertical(
                                top: Radius.circular(AppRadii.lg),
                              ),
                              child: (c.imageUrl == null ||
                                      c.imageUrl!.trim().isEmpty)
                                  ? ColoredBox(
                                      color: AppColors.primarySoft,
                                      child: Center(
                                        child: CategoryGlyphAvatar(
                                          name: c.name,
                                          size: 56,
                                        ),
                                      ),
                                    )
                                  : ResilientNetworkImage(
                                      url: c.imageUrl,
                                      fit: BoxFit.cover,
                                    ),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.all(AppSpace.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  c.name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.primaryDeep,
                                  ),
                                ),
                                if (c.children.isNotEmpty)
                                  Text(
                                    '${c.children.length} subcategories',
                                    style: Theme.of(
                                      context,
                                    ).textTheme.bodySmall,
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: AppSpace.xl),
              OutlinedButton(
                onPressed: () => context.push('/catalog'),
                child: const Text('Browse all products'),
              ),
            ],
          );
  }
}
