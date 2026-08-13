import 'package:dio/dio.dart';
import 'package:nursery_admin_mobile/core/config.dart';

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
      'API not ready. Check MySQL and Laravel .env.',
    );
  } catch (_) {
    return ApiHealthResult.down(
      'Cannot reach API (${AppConfig.apiBaseUrl}). '
      'Physical device needs LAN IP + php artisan serve --host=0.0.0.0',
    );
  }
}
