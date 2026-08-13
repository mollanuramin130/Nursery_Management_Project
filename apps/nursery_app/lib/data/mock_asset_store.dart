import 'dart:convert';

import 'package:flutter/services.dart';

/// Loads API-envelope JSON from `assets/mock_data/`.
class MockAssetStore {
  MockAssetStore({AssetBundle? bundle}) : _bundle = bundle ?? rootBundle;

  final AssetBundle _bundle;
  final Map<String, Map<String, dynamic>> _cache = {};

  static const placeholderImage =
      'assets/mock_data/images/plant_placeholder.png';

  Future<Map<String, dynamic>> loadEnvelope(String fileName) async {
    final hit = _cache[fileName];
    if (hit != null) return hit;
    final raw = await _bundle.loadString('assets/mock_data/$fileName');
    final decoded = jsonDecode(raw);
    if (decoded is! Map) {
      throw StateError('Mock $fileName is not a JSON object');
    }
    final map = Map<String, dynamic>.from(decoded);
    _cache[fileName] = map;
    return map;
  }

  Future<dynamic> dataOf(String fileName) async {
    final env = await loadEnvelope(fileName);
    return env['data'];
  }

  void clearMemoryCache() => _cache.clear();
}
