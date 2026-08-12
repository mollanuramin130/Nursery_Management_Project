import 'package:flutter/foundation.dart';

class AppConfig {
  /// Override with HTTPS in production, e.g.:
  /// `--dart-define=API_BASE_URL=https://api.example.com/api/v1`
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const storeName = String.fromEnvironment(
    'STORE_NAME',
    defaultValue: 'GreenLeaf Nursery',
  );

  /// Online payments (Razorpay). Default on for local stub / sandbox.
  /// Disable with `--dart-define=ONLINE_PAYMENTS_ENABLED=false`.
  static const onlinePaymentsEnabled = bool.fromEnvironment(
    'ONLINE_PAYMENTS_ENABLED',
    defaultValue: true,
  );

  static const appVersion = '1.0.0';
  static const platform = 'android';

  static bool get usesCleartextHttp =>
      apiBaseUrl.startsWith('http://') && !apiBaseUrl.startsWith('https://');

  static bool get isLocalDevHost {
    final u = apiBaseUrl.toLowerCase();
    return u.contains('10.0.2.2') ||
        u.contains('localhost') ||
        u.contains('127.0.0.1');
  }

  /// Call from [main] before [runApp]. Release builds must not ship emulator HTTP defaults.
  static void assertReleaseConfiguration() {
    if (!kReleaseMode) return;
    if (usesCleartextHttp || isLocalDevHost) {
      throw StateError(
        'Release build refused: set a production HTTPS API via '
        '--dart-define=API_BASE_URL=https://your-api.example/api/v1 '
        '(current: $apiBaseUrl)',
      );
    }
  }
}
