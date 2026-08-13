import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/auth_messages.dart';
import 'package:nursery_app/core/password_rules.dart';

void main() {
  group('AuthMessages', () {
    test('maps credentials', () {
      expect(
        AuthMessages.sanitize('Invalid email or password', statusCode: 401),
        'Invalid email or password.',
      );
    });

    test('maps blocked', () {
      expect(
        AuthMessages.sanitize('Account is blocked', statusCode: 401),
        'Your account is currently unavailable. Please contact support.',
      );
    });

    test('maps too many attempts', () {
      expect(
        AuthMessages.sanitize('Too Many Attempts.', statusCode: 429),
        contains('wait about a minute'),
      );
    });
  });

  group('PasswordRules', () {
    test('rejects weak passwords', () {
      expect(PasswordRules.validate('short'), isNotNull);
      expect(PasswordRules.validate('alllowercase1'), isNotNull);
      expect(PasswordRules.validate('ALLUPPERCASE1'), isNotNull);
      expect(PasswordRules.validate('NoDigitsHere'), isNotNull);
    });

    test('accepts API-aligned password', () {
      expect(PasswordRules.validate('Secret@123'), isNull);
    });
  });
}
