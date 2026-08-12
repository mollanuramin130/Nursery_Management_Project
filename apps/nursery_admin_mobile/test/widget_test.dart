import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/config.dart';

void main() {
  test('AppConfig defaults are local-dev safe', () {
    expect(AppConfig.appName, contains('GreenLeaf'));
    expect(AppConfig.apiBaseUrl.contains('/api/v1'), isTrue);
  });
}
