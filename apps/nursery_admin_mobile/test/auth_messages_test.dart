import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';

void main() {
  group('ApiException.userMessage', () {
    test('maps invalid credentials', () {
      final e = ApiException(
        'Invalid email or password',
        statusCode: 401,
        errorCode: 'AUTH_INVALID_CREDENTIALS',
      );
      expect(e.userMessage, 'Invalid email or password.');
    });

    test('maps blocked account', () {
      final e = ApiException(
        'Account is blocked',
        statusCode: 401,
        errorCode: 'AUTH_ACCOUNT_BLOCKED',
      );
      expect(
        e.userMessage,
        'Your account is currently unavailable. Please contact support.',
      );
    });

    test('maps rate limit', () {
      final e = ApiException('Too Many Attempts.', statusCode: 429);
      expect(
        e.userMessage,
        contains('wait'),
      );
    });
  });
}
