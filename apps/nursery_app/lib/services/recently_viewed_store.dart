import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class RecentProduct {
  RecentProduct({
    required this.id,
    required this.slug,
    required this.name,
    this.thumbnailUrl,
    this.price,
  });

  final int id;
  final String slug;
  final String name;
  final String? thumbnailUrl;
  final double? price;

  Map<String, dynamic> toJson() => {
        'id': id,
        'slug': slug,
        'name': name,
        'thumbnail_url': thumbnailUrl,
        'price': price,
      };

  factory RecentProduct.fromJson(Map<String, dynamic> json) => RecentProduct(
        id: (json['id'] as num).toInt(),
        slug: json['slug']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
        thumbnailUrl: json['thumbnail_url']?.toString(),
        price: (json['price'] as num?)?.toDouble(),
      );
}

class RecentlyViewedStore {
  static const _key = 'gl_recently_viewed';
  static const _max = 12;
  static const _storage = FlutterSecureStorage();

  static Future<List<RecentProduct>> list({int? excludeId}) async {
    final raw = await _storage.read(key: _key);
    if (raw == null || raw.isEmpty) return const [];
    try {
      final list = (jsonDecode(raw) as List)
          .whereType<Map>()
          .map((e) => RecentProduct.fromJson(Map<String, dynamic>.from(e)))
          .where((p) => excludeId == null || p.id != excludeId)
          .toList();
      return list;
    } catch (_) {
      return const [];
    }
  }

  static Future<void> track(RecentProduct product) async {
    final current = await list();
    final next = [
      product,
      ...current.where((p) => p.id != product.id && p.slug != product.slug),
    ].take(_max).map((p) => p.toJson()).toList();
    await _storage.write(key: _key, value: jsonEncode(next));
  }
}
