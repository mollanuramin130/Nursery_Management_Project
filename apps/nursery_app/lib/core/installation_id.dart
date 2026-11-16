import 'dart:math';

import 'package:nursery_app/core/session_storage.dart';

/// Manages the cryptographically secure, anonymous installation UUID.
///
/// **Privacy Guarantee (Option C):**
/// - This identifier is installation-scoped and generated locally on-device.
/// - It is NEVER derived from, correlated with, or mapped to customer accounts,
///   emails, phone numbers, database primary keys, hardware MAC/IMEI, or advertising IDs.
/// - It persists locally so that diagnostic crash events from the same installation
///   can be correlated across launches without compromising customer anonymity.
/// - If application storage is cleared or the app is uninstalled, a fresh UUID
///   will be generated.
class InstallationIdManager {
  static const String storageKey = 'gl_anonymous_installation_uuid';
  static String? _cachedId;

  /// Generates a standard RFC 4122 version 4 UUID using [Random.secure].
  static String generateUuidV4([Random? random]) {
    final rng = random ?? Random.secure();
    final bytes = List<int>.generate(16, (_) => rng.nextInt(256));

    // Set version 4 (0100 in high nibble of byte 6)
    bytes[6] = (bytes[6] & 0x0F) | 0x40;
    // Set variant to RFC 4122 (10 in top two bits of byte 8)
    bytes[8] = (bytes[8] & 0x3F) | 0x80;

    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-'
        '${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-'
        '${hex.substring(16, 20)}-'
        '${hex.substring(20, 32)}';
  }

  /// Retrieves an existing installation UUID or generates and persists a new one.
  static Future<String> getOrCreate(SessionStorage storage) async {
    if (_cachedId != null && _cachedId!.trim().isNotEmpty) {
      return _cachedId!;
    }
    final existing = await storage.getInstallationId();
    if (existing != null && existing.trim().isNotEmpty) {
      _cachedId = existing.trim();
      return _cachedId!;
    }
    final newId = generateUuidV4();
    await storage.saveInstallationId(newId);
    _cachedId = newId;
    return newId;
  }

  /// Reset internal in-memory cache (used for unit test isolation).
  static void resetCacheForTesting() {
    _cachedId = null;
  }
}
