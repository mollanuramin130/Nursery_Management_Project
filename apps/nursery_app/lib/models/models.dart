class User {
  User({required this.id, required this.name, required this.email, this.phone});

  final int id;
  final String name;
  final String email;
  final String? phone;

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: json['id'] as int,
    name: json['name'] as String,
    email: json['email'] as String,
    phone: json['phone'] as String?,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'phone': phone,
  };
}

class ProductSummary {
  ProductSummary({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    this.compareAtPrice,
    this.currency = 'INR',
    this.thumbnailUrl,
    this.productType,
    this.ratingAvg,
    this.ratingCount,
    this.stockStatus,
    this.badges = const [],
    this.scientificName,
  });

  final int id;
  final String name;
  final String slug;
  final double price;
  final double? compareAtPrice;
  final String currency;
  final String? thumbnailUrl;
  final String? productType;
  final double? ratingAvg;
  final int? ratingCount;
  final String? stockStatus;
  final List<String> badges;
  final String? scientificName;

  bool get isOutOfStock => stockStatus == 'out_of_stock';
  bool get isLowStock => stockStatus == 'low_stock';
  bool get hasDiscount => compareAtPrice != null && compareAtPrice! > price;

  factory ProductSummary.fromJson(Map<String, dynamic> json) {
    final plant = json['plant'];
    String? scientific;
    if (plant is Map && plant['scientific_name'] != null) {
      scientific = plant['scientific_name'].toString();
    }
    final badgesRaw = json['badges'];
    final badges = <String>[];
    if (badgesRaw is List) {
      for (final b in badgesRaw) {
        if (b != null) badges.add(b.toString());
      }
    }
    return ProductSummary(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      price: (json['price'] as num).toDouble(),
      compareAtPrice: (json['compare_at_price'] as num?)?.toDouble(),
      currency: json['currency'] as String? ?? 'INR',
      thumbnailUrl: json['thumbnail_url'] as String?,
      productType: json['product_type'] as String?,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble(),
      ratingCount: (json['rating_count'] as num?)?.toInt(),
      stockStatus: json['stock_status'] as String?,
      badges: badges,
      scientificName: scientific,
    );
  }
}

class PlantProfile {
  PlantProfile({
    this.commonName,
    this.scientificName,
    this.sunlight,
    this.waterRequirement,
    this.soilType,
    this.indoorOutdoor,
    this.difficultyLevel,
    this.careLevel,
    this.petSafety,
    this.growingInstructions,
    this.temperatureMinC,
    this.temperatureMaxC,
  });

  final String? commonName;
  final String? scientificName;
  final String? sunlight;
  final String? waterRequirement;
  final String? soilType;
  final String? indoorOutdoor;
  final String? difficultyLevel;
  final String? careLevel;
  final String? petSafety;
  final String? growingInstructions;
  final double? temperatureMinC;
  final double? temperatureMaxC;

  factory PlantProfile.fromJson(Map<String, dynamic> json) => PlantProfile(
    commonName: json['common_name'] as String?,
    scientificName: json['scientific_name'] as String?,
    sunlight: json['sunlight'] as String?,
    waterRequirement: json['water_requirement'] as String?,
    soilType: json['soil_type'] as String?,
    indoorOutdoor: json['indoor_outdoor'] as String?,
    difficultyLevel: json['difficulty_level'] as String?,
    careLevel: json['care_level'] as String?,
    petSafety: json['pet_safety'] as String?,
    growingInstructions: json['growing_instructions'] as String?,
    temperatureMinC: (json['temperature_min_c'] as num?)?.toDouble(),
    temperatureMaxC: (json['temperature_max_c'] as num?)?.toDouble(),
  );
}

class ProductDetail {
  ProductDetail({
    required this.summary,
    this.description,
    this.sku,
    this.availableQty,
    this.images = const [],
    this.plant,
    this.subscriptionEnabled = false,
    this.subscriptionPlans = const [],
  });

  final ProductSummary summary;
  final String? description;
  final String? sku;
  final int? availableQty;
  final List<String> images;
  final PlantProfile? plant;
  final bool subscriptionEnabled;
  final List<SubscriptionPlanOption> subscriptionPlans;

  factory ProductDetail.fromJson(Map<String, dynamic> json) {
    final images = <String>[];
    final rawImages = json['images'];
    if (rawImages is List) {
      for (final img in rawImages) {
        if (img is Map && img['url'] != null) {
          images.add(img['url'].toString());
        }
      }
    }
    final plans = <SubscriptionPlanOption>[];
    final rawPlans = json['subscription_plans'];
    if (rawPlans is List) {
      for (final p in rawPlans) {
        if (p is Map) {
          plans.add(
            SubscriptionPlanOption.fromJson(Map<String, dynamic>.from(p)),
          );
        }
      }
    }
    return ProductDetail(
      summary: ProductSummary.fromJson(json),
      description: json['description'] as String?,
      sku: json['sku'] as String?,
      availableQty: (json['available_qty'] as num?)?.toInt(),
      images: images,
      plant: json['plant'] is Map
          ? PlantProfile.fromJson(
              Map<String, dynamic>.from(json['plant'] as Map),
            )
          : null,
      subscriptionEnabled: json['subscription_enabled'] == true,
      subscriptionPlans: plans,
    );
  }
}

class SubscriptionPlanOption {
  SubscriptionPlanOption({
    required this.id,
    required this.name,
    required this.frequency,
    required this.unitPrice,
    this.quantityDefault = 1,
  });

  final int id;
  final String name;
  final String frequency;
  final double unitPrice;
  final int quantityDefault;

  factory SubscriptionPlanOption.fromJson(Map<String, dynamic> json) =>
      SubscriptionPlanOption(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? 'Plan',
        frequency: json['frequency']?.toString() ?? 'MONTHLY',
        unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0,
        quantityDefault: (json['quantity_default'] as num?)?.toInt() ?? 1,
      );
}

class ProductReview {
  ProductReview({
    required this.id,
    required this.rating,
    required this.userName,
    this.title,
    this.body,
    this.createdAt,
    this.images = const [],
    this.verifiedPurchase = false,
  });

  final int id;
  final int rating;
  final String userName;
  final String? title;
  final String? body;
  final String? createdAt;
  final List<String> images;
  final bool verifiedPurchase;

  factory ProductReview.fromJson(Map<String, dynamic> json) => ProductReview(
    id: json['id'] as int,
    rating: (json['rating'] as num).toInt(),
    userName: json['user_name']?.toString() ?? 'Customer',
    title: json['title'] as String?,
    body: json['body'] as String?,
    createdAt: json['created_at'] as String?,
    verifiedPurchase: json['verified_purchase'] == true,
    images: ((json['images'] as List?) ?? [])
        .map((e) => e.toString())
        .where((e) => e.isNotEmpty)
        .toList(),
  );
}

class BannerItem {
  BannerItem({
    required this.id,
    required this.title,
    required this.imageUrl,
    this.linkType,
    this.linkValue,
  });

  final int id;
  final String title;
  final String imageUrl;
  final String? linkType;
  final String? linkValue;

  factory BannerItem.fromJson(Map<String, dynamic> json) => BannerItem(
    id: json['id'] as int,
    title: json['title'] as String,
    imageUrl: (json['image_url'] as String?) ?? '',
    linkType: json['link_type'] as String?,
    linkValue: json['link_value'] as String?,
  );
}

class CategoryChip {
  CategoryChip({
    required this.name,
    required this.slug,
    this.imageUrl,
    this.parentId,
  });

  final String name;
  final String slug;
  final String? imageUrl;
  final int? parentId;
}

class HomeFeed {
  HomeFeed({
    required this.banners,
    required this.featuredProducts,
    required this.campaigns,
    this.categories = const [],
    this.newArrivals = const [],
    this.bestSellers = const [],
    this.recommendedForYou = const [],
    this.indoorPlants = const [],
    this.outdoorPlants = const [],
    this.lowMaintenance = const [],
  });

  final List<BannerItem> banners;
  final List<ProductSummary> featuredProducts;
  final List<Map<String, dynamic>> campaigns;
  final List<CategoryChip> categories;
  final List<ProductSummary> newArrivals;
  final List<ProductSummary> bestSellers;
  final List<ProductSummary> recommendedForYou;
  final List<ProductSummary> indoorPlants;
  final List<ProductSummary> outdoorPlants;
  final List<ProductSummary> lowMaintenance;

  factory HomeFeed.fromJson(Map<String, dynamic> json) {
    List<ProductSummary> productsOf(String key) => ((json[key] as List?) ?? [])
        .whereType<Map>()
        .map((e) => ProductSummary.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    return HomeFeed(
      banners: ((json['banners'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => BannerItem.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredProducts: productsOf('featured_products'),
      newArrivals: productsOf('new_arrivals'),
      bestSellers: productsOf('best_sellers'),
      recommendedForYou: productsOf('recommended_for_you'),
      indoorPlants: productsOf('indoor_plants'),
      outdoorPlants: productsOf('outdoor_plants'),
      lowMaintenance: productsOf('low_maintenance'),
      campaigns: ((json['campaigns'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      categories: ((json['categories'] as List?) ?? []).whereType<Map>().map((
        e,
      ) {
        final m = Map<String, dynamic>.from(e);
        return CategoryChip(
          name: m['name'] as String,
          slug: m['slug'] as String,
          imageUrl: (m['image_url'] ?? m['image'] ?? m['thumbnail_url'])
              as String?,
          parentId: m['parent_id'] as int?,
        );
      }).toList(),
    );
  }
}

class CartItem {
  CartItem({
    required this.id,
    required this.productId,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.lineTotal,
    this.variantId,
    this.thumbnailUrl,
    this.slug,
    this.stockStatus,
    this.maxQty,
  });

  final int id;
  final int productId;
  /// Canonical API field (`variant_id`). DB column remains product_variant_id.
  final int? variantId;
  final String name;
  final double unitPrice;
  final int quantity;
  final double lineTotal;
  final String? thumbnailUrl;
  final String? slug;
  final String? stockStatus;
  final int? maxQty;

  bool get isUnavailable =>
      stockStatus == 'out_of_stock' || (maxQty != null && maxQty! <= 0);

  factory CartItem.fromJson(Map<String, dynamic> json) => CartItem(
    id: json['id'] as int,
    productId: json['product_id'] as int,
    variantId: (json['variant_id'] as num?)?.toInt(),
    name: json['name'] as String,
    unitPrice: (json['unit_price'] as num).toDouble(),
    quantity: json['quantity'] as int,
    lineTotal: (json['line_total'] as num).toDouble(),
    thumbnailUrl: json['thumbnail_url'] as String?,
    slug: json['slug'] as String?,
    stockStatus: json['stock_status'] as String?,
    maxQty: (json['max_qty'] as num?)?.toInt(),
  );
}

class FreeDelivery {
  FreeDelivery({
    required this.enabled,
    required this.threshold,
    required this.remaining,
    required this.qualifies,
  });

  final bool enabled;
  final double threshold;
  final double remaining;
  final bool qualifies;

  factory FreeDelivery.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return FreeDelivery(
        enabled: true,
        threshold: 999,
        remaining: 999,
        qualifies: false,
      );
    }
    return FreeDelivery(
      enabled: json['enabled'] as bool? ?? true,
      threshold: (json['threshold'] as num?)?.toDouble() ?? 999,
      remaining: (json['remaining'] as num?)?.toDouble() ?? 0,
      qualifies: json['qualifies'] as bool? ?? false,
    );
  }
}

class Cart {
  Cart({
    required this.items,
    required this.itemCount,
    required this.subtotal,
    required this.discountTotal,
    required this.grandTotal,
    this.taxTotal = 0,
    this.shippingTotal = 0,
    this.couponCode,
    this.currency = 'INR',
    FreeDelivery? freeDelivery,
    this.warnings = const [],
    this.checkoutBlocked = false,
  }) : freeDelivery = freeDelivery ?? FreeDelivery.fromJson(null);

  final List<CartItem> items;
  final int itemCount;
  final double subtotal;
  final double discountTotal;
  final double grandTotal;
  final double taxTotal;
  final double shippingTotal;
  final String? couponCode;
  final String currency;
  final FreeDelivery freeDelivery;
  final List<CartWarning> warnings;
  final bool checkoutBlocked;

  factory Cart.fromJson(Map<String, dynamic> json) => Cart(
    items: ((json['items'] as List?) ?? [])
        .whereType<Map>()
        .map((e) => CartItem.fromJson(Map<String, dynamic>.from(e)))
        .toList(),
    itemCount: json['item_count'] as int? ?? 0,
    subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0,
    discountTotal: (json['discount_total'] as num?)?.toDouble() ?? 0,
    taxTotal: (json['tax_total'] as num?)?.toDouble() ?? 0,
    shippingTotal: (json['shipping_total'] as num?)?.toDouble() ?? 0,
    grandTotal: (json['grand_total'] as num?)?.toDouble() ?? 0,
    couponCode: json['coupon_code'] as String?,
    currency: json['currency'] as String? ?? 'INR',
    freeDelivery: FreeDelivery.fromJson(
      json['free_delivery'] is Map
          ? Map<String, dynamic>.from(json['free_delivery'] as Map)
          : null,
    ),
    warnings: ((json['warnings'] as List?) ?? [])
        .whereType<Map>()
        .map((e) => CartWarning.fromJson(Map<String, dynamic>.from(e)))
        .toList(),
    checkoutBlocked: json['checkout_blocked'] as bool? ?? false,
  );

  factory Cart.empty() => Cart(
    items: const [],
    itemCount: 0,
    subtotal: 0,
    discountTotal: 0,
    grandTotal: 0,
  );
}

class CartWarning {
  CartWarning({
    required this.code,
    required this.severity,
    required this.message,
    this.productId,
    this.blocking = false,
  });

  final String code;
  final String severity;
  final String message;
  final int? productId;
  final bool blocking;

  factory CartWarning.fromJson(Map<String, dynamic> json) => CartWarning(
    code: json['code']?.toString() ?? '',
    severity: json['severity']?.toString() ?? 'warning',
    message: json['message']?.toString() ?? '',
    productId: (json['product_id'] as num?)?.toInt(),
    blocking: json['blocking'] as bool? ?? false,
  );
}

class Address {
  Address({
    required this.id,
    required this.name,
    required this.phone,
    required this.line1,
    required this.city,
    required this.state,
    required this.postalCode,
    this.line2,
    this.label,
    this.country = 'IN',
    this.isDefault = false,
  });

  final int id;
  final String name;
  final String phone;
  final String line1;
  final String? line2;
  final String city;
  final String state;
  final String postalCode;
  final String? label;
  final String country;
  final bool isDefault;

  factory Address.fromJson(Map<String, dynamic> json) => Address(
    id: json['id'] as int,
    name: json['name'] as String,
    phone: json['phone'] as String,
    line1: json['line1'] as String,
    line2: json['line2'] as String?,
    city: json['city'] as String,
    state: json['state'] as String,
    postalCode: json['postal_code'] as String,
    label: json['label'] as String?,
    country: json['country'] as String? ?? 'IN',
    isDefault: json['is_default'] == true,
  );
}

class ShippingMethod {
  ShippingMethod({
    required this.id,
    required this.code,
    required this.name,
    required this.price,
  });

  final int id;
  final String code;
  final String name;
  final double price;

  factory ShippingMethod.fromJson(Map<String, dynamic> json) => ShippingMethod(
    id: json['id'] as int,
    code: json['code'] as String? ?? '',
    name: json['name'] as String,
    price: (json['price'] as num?)?.toDouble() ?? 0,
  );
}

class OrderSummary {
  OrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.grandTotal,
    this.itemCount,
    this.createdAt,
    this.placedAt,
    this.thumbnail,
    this.previewName,
    this.estimatedDelivery,
    this.canCancel = false,
    this.canReorder = false,
    this.canReturn = false,
    this.paymentStatus,
    this.currency = 'INR',
  });

  final int id;
  final String orderNumber;
  final String status;
  final double grandTotal;
  final int? itemCount;
  final String? createdAt;
  final String? placedAt;
  final String? thumbnail;
  final String? previewName;
  final String? estimatedDelivery;
  final bool canCancel;
  final bool canReorder;
  final bool canReturn;
  final String? paymentStatus;
  final String currency;

  factory OrderSummary.fromJson(Map<String, dynamic> json) => OrderSummary(
    id: json['id'] as int,
    orderNumber: json['order_number'] as String,
    status: json['status'] as String,
    grandTotal: (json['grand_total'] as num).toDouble(),
    itemCount: (json['item_count'] as num?)?.toInt(),
    createdAt: json['created_at'] as String?,
    placedAt: json['placed_at'] as String?,
    thumbnail: json['thumbnail'] as String?,
    previewName: json['preview_name'] as String?,
    estimatedDelivery: json['estimated_delivery'] as String?,
    canCancel: json['can_cancel'] == true,
    canReorder: json['can_reorder'] == true,
    canReturn: json['can_return'] == true,
    paymentStatus: json['payment_status'] as String?,
    currency: json['currency'] as String? ?? 'INR',
  );
}

class OrderTrackingStep {
  OrderTrackingStep({
    required this.status,
    required this.title,
    required this.completed,
    this.description,
    this.current = false,
    this.createdAt,
  });

  final String status;
  final String title;
  final String? description;
  final bool completed;
  final bool current;
  final String? createdAt;

  factory OrderTrackingStep.fromJson(Map<String, dynamic> json) =>
      OrderTrackingStep(
        status: json['status']?.toString() ?? '',
        title: json['title']?.toString() ?? json['status']?.toString() ?? '',
        description: json['description']?.toString(),
        completed: json['completed'] == true,
        current: json['current'] == true,
        createdAt: json['created_at'] as String?,
      );
}

class OrderDetail {
  OrderDetail({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.grandTotal,
    required this.subtotal,
    required this.discountTotal,
    required this.taxTotal,
    required this.shippingTotal,
    required this.items,
    this.currency = 'INR',
    this.payment,
    this.paymentMethod,
    this.shippingAddress,
    this.shipment,
    this.tracking,
    this.statusHistory = const [],
    this.createdAt,
    this.placedAt,
    this.cancelReason,
    this.cancelledAt,
    this.canCancel = false,
    this.canReorder = false,
    this.canReturn = false,
    this.returns = const [],
    this.refunds = const [],
    this.returnReasons = const [],
    this.returnableItems = const [],
    this.reviewableProductIds = const [],
  });

  final int id;
  final String orderNumber;
  final String status;
  final String currency;
  final double grandTotal;
  final double subtotal;
  final double discountTotal;
  final double taxTotal;
  final double shippingTotal;
  final List<OrderLineItem> items;
  final Map<String, dynamic>? payment;
  final String? paymentMethod;
  final Map<String, dynamic>? shippingAddress;
  final Map<String, dynamic>? shipment;
  final Map<String, dynamic>? tracking;
  final List<Map<String, dynamic>> statusHistory;
  final String? createdAt;
  final String? placedAt;
  final String? cancelReason;
  final String? cancelledAt;
  final bool canCancel;
  final bool canReorder;
  final bool canReturn;
  final List<Map<String, dynamic>> returns;
  final List<Map<String, dynamic>> refunds;
  final List<Map<String, dynamic>> returnReasons;
  final List<Map<String, dynamic>> returnableItems;
  final List<int> reviewableProductIds;

  List<OrderTrackingStep> get timeline {
    final raw = tracking?['timeline'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => OrderTrackingStep.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  factory OrderDetail.fromJson(Map<String, dynamic> json) {
    final actions = json['actions'] is Map
        ? Map<String, dynamic>.from(json['actions'] as Map)
        : null;
    return OrderDetail(
      id: json['id'] as int,
      orderNumber: json['order_number'] as String,
      status: json['status'] as String,
      currency: json['currency'] as String? ?? 'INR',
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0,
      discountTotal: (json['discount_total'] as num?)?.toDouble() ?? 0,
      taxTotal: (json['tax_total'] as num?)?.toDouble() ?? 0,
      shippingTotal: (json['shipping_total'] as num?)?.toDouble() ?? 0,
      grandTotal: (json['grand_total'] as num).toDouble(),
      payment: json['payment'] is Map
          ? Map<String, dynamic>.from(json['payment'] as Map)
          : null,
      paymentMethod: json['payment_method'] as String?,
      shippingAddress: json['shipping_address'] is Map
          ? Map<String, dynamic>.from(json['shipping_address'] as Map)
          : null,
      shipment: json['shipment'] is Map
          ? Map<String, dynamic>.from(json['shipment'] as Map)
          : null,
      tracking: json['tracking'] is Map
          ? Map<String, dynamic>.from(json['tracking'] as Map)
          : null,
      statusHistory: ((json['status_history'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      items: ((json['items'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => OrderLineItem.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      createdAt: json['created_at'] as String?,
      placedAt: json['placed_at'] as String?,
      cancelReason: json['cancel_reason'] as String?,
      cancelledAt: json['cancelled_at'] as String?,
      canCancel: actions?['can_cancel'] == true || json['can_cancel'] == true,
      canReorder: actions?['can_reorder'] == true || json['can_reorder'] == true,
      canReturn: actions?['can_return'] == true || json['can_return'] == true,
      returns: ((json['returns'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      refunds: ((json['refunds'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      returnReasons: ((json['return_reasons'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      returnableItems: ((json['returnable_items'] as List?) ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      reviewableProductIds: ((json['reviewable_product_ids'] as List?) ?? [])
          .map((e) => (e as num).toInt())
          .toList(),
    );
  }

  int returnableQtyFor(int orderItemId) {
    for (final row in returnableItems) {
      if ((row['order_item_id'] as num?)?.toInt() == orderItemId) {
        return (row['returnable_qty'] as num?)?.toInt() ?? 0;
      }
    }
    return 0;
  }
}

class OrderLineItem {
  OrderLineItem({
    required this.id,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.lineTotal,
    this.thumbnailUrl,
    this.productId,
    this.productSlug,
  });

  final int id;
  final String name;
  final double unitPrice;
  final int quantity;
  final double lineTotal;
  final String? thumbnailUrl;
  final int? productId;
  final String? productSlug;

  factory OrderLineItem.fromJson(Map<String, dynamic> json) => OrderLineItem(
    id: json['id'] as int,
    name: json['name'] as String,
    unitPrice: (json['unit_price'] as num).toDouble(),
    quantity: json['quantity'] as int,
    lineTotal: (json['line_total'] as num).toDouble(),
    thumbnailUrl: json['thumbnail_url'] as String?,
    productId: (json['product_id'] as num?)?.toInt(),
    productSlug: json['product_slug'] as String?,
  );
}

class WishlistRow {
  WishlistRow({required this.product});

  final ProductSummary product;

  factory WishlistRow.fromJson(Map<String, dynamic> json) => WishlistRow(
    product: ProductSummary.fromJson(
      Map<String, dynamic>.from(json['product'] as Map),
    ),
  );
}

class PlantCareGuide {
  PlantCareGuide({
    this.sunlightLabel,
    this.waterLabel,
    this.soilType,
    this.difficultyLevel,
    this.growingInstructions,
    this.plantingInstructions,
    this.pruningInstructions,
    this.fertilizationInstructions,
    this.pestDiseaseInfo,
    this.toxicityInfo,
    this.petSafety,
    this.temperatureMinC,
    this.temperatureMaxC,
  });

  final String? sunlightLabel;
  final String? waterLabel;
  final String? soilType;
  final String? difficultyLevel;
  final String? growingInstructions;
  final String? plantingInstructions;
  final String? pruningInstructions;
  final String? fertilizationInstructions;
  final String? pestDiseaseInfo;
  final String? toxicityInfo;
  final String? petSafety;
  final double? temperatureMinC;
  final double? temperatureMaxC;

  factory PlantCareGuide.fromJson(Map<String, dynamic> json) => PlantCareGuide(
    sunlightLabel: json['sunlight_label'] as String?,
    waterLabel: json['water_label'] as String?,
    soilType: json['soil_type'] as String?,
    difficultyLevel: json['difficulty_level'] as String?,
    growingInstructions: json['growing_instructions'] as String?,
    plantingInstructions: json['planting_instructions'] as String?,
    pruningInstructions: json['pruning_instructions'] as String?,
    fertilizationInstructions: json['fertilization_instructions'] as String?,
    pestDiseaseInfo: json['pest_disease_info'] as String?,
    toxicityInfo: json['toxicity_info'] as String?,
    petSafety: json['pet_safety'] as String?,
    temperatureMinC: (json['temperature_min_c'] as num?)?.toDouble(),
    temperatureMaxC: (json['temperature_max_c'] as num?)?.toDouble(),
  );
}

String money(num amount, [String currency = 'INR']) {
  final cents = (amount * 100).round();
  final whole = cents ~/ 100;
  final frac = cents % 100;
  final wholeStr = whole.toString().replaceAllMapped(
    RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
    (m) => '${m[1]},',
  );
  if (frac == 0) return '₹$wholeStr';
  return '₹$wholeStr.${frac.toString().padLeft(2, '0')}';
}

/// Formats API timestamps for locale-friendly display.
String formatOrderDate(String? raw) {
  if (raw == null || raw.isEmpty) return '';
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return raw;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final local = parsed.toLocal();
  final h = local.hour % 12 == 0 ? 12 : local.hour % 12;
  final m = local.minute.toString().padLeft(2, '0');
  final ampm = local.hour >= 12 ? 'PM' : 'AM';
  return '${local.day} ${months[local.month - 1]} ${local.year}, $h:$m $ampm';
}
