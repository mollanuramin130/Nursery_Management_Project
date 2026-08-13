import 'package:dio/dio.dart';
import 'package:nursery_app/core/config.dart';

/// Result of GET /health/ready (no auth, no secrets logged).
class ApiHealthResult {
  const ApiHealthResult.ok() : ok = true, message = null;
  const ApiHealthResult.down(this.message) : ok = false;

  final bool ok;
  final String? message;
}

Future<ApiHealthResult> probeApiHealth() async {
  try {
    final dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 8),
        receiveTimeout: const Duration(seconds: 8),
        headers: {'Accept': 'application/json'},
      ),
    );
    final res = await dio.get('/health/ready');
    final data = res.data;
    if (data is Map &&
        (data['status'] == 'ok' || data['database'] == 'healthy')) {
      return const ApiHealthResult.ok();
    }
    return const ApiHealthResult.down(
      'API responded but is not ready. Check MySQL and Laravel .env.',
    );
  } catch (_) {
    return ApiHealthResult.down(
      'Cannot reach API at ${AppConfig.apiBaseUrl}. '
      'Emulator: default 10.0.2.2. Physical phone: '
      'flutter run --dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1 '
      'and php artisan serve --host=0.0.0.0',
    );
  }
}
