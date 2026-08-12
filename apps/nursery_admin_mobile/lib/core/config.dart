import 'package:flutter/foundation.dart';

/// GreenLeaf Operations — Admin Mobile configuration.
///
/// Override API with:
/// `--dart-define=API_BASE_URL=https://api.example.com/api/v1`
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const appName = String.fromEnvironment(
    'APP_NAME',
    defaultValue: 'GreenLeaf Operations',
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

  static void assertReleaseConfiguration() {
    if (!kReleaseMode) return;
    if (usesCleartextHttp || isLocalDevHost) {
      throw StateError(
        'Release build refused: set production HTTPS API via '
        '--dart-define=API_BASE_URL=https://your-api.example/api/v1 '
        '(current: $apiBaseUrl)',
      );
    }
  }
}
