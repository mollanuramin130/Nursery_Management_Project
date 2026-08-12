import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_bottom_sheet.dart';

/// Catalog listing / filter state aligned with web + API query keys.
class CatalogFilters {
  const CatalogFilters({
    this.q,
    this.category,
    this.productType,
    this.indoorOutdoor,
    this.sunlight,
    this.waterRequirement,
    this.difficultyLevel,
    this.availability,
    this.onSale,
    this.minPrice,
    this.maxPrice,
    this.sort = 'newest',
  });

  final String? q;
  final String? category;
  final String? productType;
  final String? indoorOutdoor;
  final String? sunlight;
  final String? waterRequirement;
  final String? difficultyLevel;
  final String? availability;
  final String? onSale;
  final String? minPrice;
  final String? maxPrice;
  final String sort;

  factory CatalogFilters.fromQuery(Map<String, String> params) {
    return CatalogFilters(
      q: _emptyToNull(params['q']),
      category: _emptyToNull(params['category']),
      productType: _emptyToNull(params['product_type'] ?? params['type']),
      indoorOutdoor: _emptyToNull(params['indoor_outdoor']),
      sunlight: _emptyToNull(params['sunlight']),
      waterRequirement: _emptyToNull(params['water_requirement']),
      difficultyLevel: _emptyToNull(params['difficulty_level']),
      availability: _emptyToNull(params['availability']),
      onSale: _emptyToNull(params['on_sale']),
      minPrice: _emptyToNull(params['min_price']),
      maxPrice: _emptyToNull(params['max_price']),
      sort: params['sort']?.isNotEmpty == true ? params['sort']! : 'newest',
    );
  }

  static String? _emptyToNull(String? v) =>
      (v == null || v.trim().isEmpty) ? null : v.trim();

  CatalogFilters copyWith({
    String? q,
    String? category,
    String? productType,
    String? indoorOutdoor,
    String? sunlight,
    String? waterRequirement,
    String? difficultyLevel,
    String? availability,
    String? onSale,
    String? minPrice,
    String? maxPrice,
    String? sort,
    bool clearQ = false,
    bool clearCategory = false,
    bool clearProductType = false,
    bool clearIndoorOutdoor = false,
    bool clearSunlight = false,
    bool clearWater = false,
    bool clearDifficulty = false,
    bool clearAvailability = false,
    bool clearOnSale = false,
    bool clearMinPrice = false,
    bool clearMaxPrice = false,
  }) {
    return CatalogFilters(
      q: clearQ ? null : (q ?? this.q),
      category: clearCategory ? null : (category ?? this.category),
      productType: clearProductType ? null : (productType ?? this.productType),
      indoorOutdoor: clearIndoorOutdoor
          ? null
          : (indoorOutdoor ?? this.indoorOutdoor),
      sunlight: clearSunlight ? null : (sunlight ?? this.sunlight),
      waterRequirement: clearWater
          ? null
          : (waterRequirement ?? this.waterRequirement),
      difficultyLevel: clearDifficulty
          ? null
          : (difficultyLevel ?? this.difficultyLevel),
      availability: clearAvailability
          ? null
          : (availability ?? this.availability),
      onSale: clearOnSale ? null : (onSale ?? this.onSale),
      minPrice: clearMinPrice ? null : (minPrice ?? this.minPrice),
      maxPrice: clearMaxPrice ? null : (maxPrice ?? this.maxPrice),
      sort: sort ?? this.sort,
    );
  }

  Map<String, dynamic> toApiQuery({int page = 1, int perPage = 24}) {
    return {
      'page': page,
      'per_page': perPage,
      'sort': sort,
      if (q != null) 'q': q,
      if (category != null) 'category': category,
      if (productType != null) 'product_type': productType,
      if (indoorOutdoor != null) 'indoor_outdoor': indoorOutdoor,
      if (sunlight != null) 'sunlight': sunlight,
      if (waterRequirement != null) 'water_requirement': waterRequirement,
      if (difficultyLevel != null) 'difficulty_level': difficultyLevel,
      if (availability != null) 'availability': availability,
      if (onSale != null) 'on_sale': onSale,
      if (minPrice != null) 'min_price': minPrice,
      if (maxPrice != null) 'max_price': maxPrice,
    };
  }

  Map<String, String> toRouteQuery() {
    final m = <String, String>{};
    void put(String k, String? v) {
      if (v != null && v.isNotEmpty) m[k] = v;
    }

    put('q', q);
    put('category', category);
    put('product_type', productType);
    put('indoor_outdoor', indoorOutdoor);
    put('sunlight', sunlight);
    put('water_requirement', waterRequirement);
    put('difficulty_level', difficultyLevel);
    put('availability', availability);
    put('on_sale', onSale);
    put('min_price', minPrice);
    put('max_price', maxPrice);
    if (sort != 'newest') put('sort', sort);
    return m;
  }

  int get activeFilterCount {
    var n = 0;
    if (productType != null) n++;
    if (indoorOutdoor != null) n++;
    if (sunlight != null) n++;
    if (waterRequirement != null) n++;
    if (difficultyLevel != null) n++;
    if (availability != null) n++;
    if (onSale != null) n++;
    if (minPrice != null || maxPrice != null) n++;
    return n;
  }

  bool get hasActiveFilters => activeFilterCount > 0;

  CatalogFilters clearedFilters() =>
      CatalogFilters(q: q, category: category, sort: sort);
}

const kProductTypes = [
  ('plant', 'Plants'),
  ('tree', 'Trees'),
  ('seed', 'Seeds'),
  ('pot', 'Pots'),
  ('soil', 'Soil'),
  ('fertilizer', 'Fertilizers'),
  ('tool', 'Tools'),
  ('kit', 'Kits'),
];

const kPlacement = [
  ('indoor', 'Indoor'),
  ('outdoor', 'Outdoor'),
  ('both', 'Indoor & outdoor'),
];

const kSunlight = [
  ('low', 'Low light'),
  ('bright_indirect', 'Bright indirect'),
  ('full_sun', 'Full sun'),
];

const kWater = [('low', 'Low'), ('medium', 'Moderate'), ('high', 'High')];

const kDifficulty = [
  ('easy', 'Beginner'),
  ('moderate', 'Intermediate'),
  ('advanced', 'Expert'),
];

const kAvailability = [('in_stock', 'In stock'), ('low_stock', 'Low stock')];

const kPricePresets = [
  (null, '299', 'Under ₹299'),
  ('300', '599', '₹300 – ₹599'),
  ('600', '999', '₹600 – ₹999'),
  ('1000', null, '₹1000+'),
];

const kSortOptions = [
  ('newest', 'Newest'),
  ('popular', 'Popularity'),
  ('price_asc', 'Price: Low to High'),
  ('price_desc', 'Price: High to Low'),
  ('rating', 'Top rated'),
];

Future<CatalogFilters?> showCatalogFilterSheet(
  BuildContext context,
  CatalogFilters current,
) {
  var draft = current;
  return AppBottomSheet.show<CatalogFilters>(
    context: context,
    title: 'Filters',
    actions: AppBottomSheetActions(
      secondaryLabel: 'Clear all',
      onSecondary: () {
        draft = current.clearedFilters();
        Navigator.pop(context, draft);
      },
      primaryLabel: 'Apply',
      onPrimary: () => Navigator.pop(context, draft),
    ),
    child: StatefulBuilder(
      builder: (context, setModal) {
        Widget section(String title, Widget child) {
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpace.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: AppSpace.sm),
                child,
              ],
            ),
          );
        }

        Widget chips(
          List<(String, String)> options,
          String? selected,
          void Function(String?) onSelect,
        ) {
          return Wrap(
            spacing: AppSpace.sm,
            runSpacing: AppSpace.sm,
            children: options.map((o) {
              final active = selected == o.$1;
              return FilterChip(
                label: Text(o.$2),
                selected: active,
                onSelected: (_) {
                  setModal(() {
                    onSelect(active ? null : o.$1);
                  });
                },
                selectedColor: AppColors.primarySoft,
                checkmarkColor: AppColors.primaryDeep,
                labelStyle: TextStyle(
                  fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                  color: active ? AppColors.primaryDeep : AppColors.inkSoft,
                ),
                side: BorderSide(
                  color: active ? AppColors.primary : AppColors.border,
                ),
              );
            }).toList(),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            section(
              'Product type',
              chips(kProductTypes, draft.productType, (v) {
                draft = draft.copyWith(
                  productType: v,
                  clearProductType: v == null,
                );
              }),
            ),
            section(
              'Placement',
              chips(kPlacement, draft.indoorOutdoor, (v) {
                draft = draft.copyWith(
                  indoorOutdoor: v,
                  clearIndoorOutdoor: v == null,
                );
              }),
            ),
            section(
              'Sunlight',
              chips(kSunlight, draft.sunlight, (v) {
                draft = draft.copyWith(sunlight: v, clearSunlight: v == null);
              }),
            ),
            section(
              'Water',
              chips(kWater, draft.waterRequirement, (v) {
                draft = draft.copyWith(
                  waterRequirement: v,
                  clearWater: v == null,
                );
              }),
            ),
            section(
              'Difficulty',
              chips(kDifficulty, draft.difficultyLevel, (v) {
                draft = draft.copyWith(
                  difficultyLevel: v,
                  clearDifficulty: v == null,
                );
              }),
            ),
            section(
              'Availability',
              chips(kAvailability, draft.availability, (v) {
                draft = draft.copyWith(
                  availability: v,
                  clearAvailability: v == null,
                );
              }),
            ),
            section(
              'Price',
              Wrap(
                spacing: AppSpace.sm,
                runSpacing: AppSpace.sm,
                children: kPricePresets.map((p) {
                  final active =
                      draft.minPrice == p.$1 && draft.maxPrice == p.$2;
                  return FilterChip(
                    label: Text(p.$3),
                    selected: active,
                    onSelected: (_) {
                      setModal(() {
                        if (active) {
                          draft = draft.copyWith(
                            clearMinPrice: true,
                            clearMaxPrice: true,
                          );
                        } else {
                          draft = draft.copyWith(
                            minPrice: p.$1,
                            maxPrice: p.$2,
                            clearMinPrice: p.$1 == null,
                            clearMaxPrice: p.$2 == null,
                          );
                        }
                      });
                    },
                    selectedColor: AppColors.primarySoft,
                    checkmarkColor: AppColors.primaryDeep,
                  );
                }).toList(),
              ),
            ),
          ],
        );
      },
    ),
  );
}

Future<String?> showCatalogSortSheet(BuildContext context, String current) {
  return AppBottomSheet.show<String>(
    context: context,
    title: 'Sort by',
    child: Column(
      children: kSortOptions
          .map(
            (o) => ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(o.$2),
              trailing: current == o.$1
                  ? const Icon(
                      Icons.check_rounded,
                      color: AppColors.primaryDeep,
                    )
                  : null,
              onTap: () => Navigator.pop(context, o.$1),
            ),
          )
          .toList(),
    ),
  );
}
