import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/theme/tokens.dart';
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
    imageUrl: json['image_url'] as String?,
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
  late Future<List<CategoryItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<CategoryItem>> _load() {
    return context.read<ApiClient>().getData(
      '/categories',
      map: (data) => (data as List)
          .whereType<Map>()
          .map((e) => CategoryItem.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Categories')),
      body: FutureBuilder<List<CategoryItem>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const CategoryGridSkeleton();
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load categories',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final cats = snap.data ?? [];
          if (cats.isEmpty) {
            return EmptyStateView(
              title: 'No categories yet',
              message: 'Browse the full shop while we grow this list.',
              actionLabel: 'Shop all',
              onAction: () => context.push('/catalog'),
              icon: Icons.grid_view_rounded,
            );
          }

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
                              child: c.imageUrl == null
                                  ? Container(
                                      color: AppColors.primarySoft,
                                      alignment: Alignment.center,
                                      child: const Icon(
                                        Icons.local_florist_rounded,
                                        color: AppColors.primaryDeep,
                                      ),
                                    )
                                  : CachedNetworkImage(
                                      imageUrl: c.imageUrl!,
                                      fit: BoxFit.cover,
                                      memCacheWidth: 600,
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
        },
      ),
    );
  }
}
