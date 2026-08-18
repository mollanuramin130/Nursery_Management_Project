import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/app_error.dart';
import 'package:nursery_admin_mobile/core/support.dart';

void main() {
  test('QA-43 helpline and reference', () {
    expect(kGreenLeafSupportPhone, '8926627220');
    expect(supportTelUri().toString(), 'tel:8926627220');
    expect(looksLikeErrorReference(newErrorReference(() => 1)), isTrue);
  });

  test('QA-43 caught errors never leak toString', () {
    expect(
      sanitizeCaughtError(StateError('Bad state: Null check used on a null value')),
      isNot(contains('Null check')),
    );
    expect(sanitizeCaughtError(ApiException('boom', statusCode: 500)).toLowerCase(),
        contains('unavailable'));
  });

  test('QA-43 5xx is not raw PHP/sql', () {
    final e = ApiException('SQLSTATE explode /vendor/laravel', statusCode: 500);
    expect(e.userMessage.toLowerCase(), isNot(contains('sql')));
    expect(e.userMessage.toLowerCase(), isNot(contains('vendor')));
  });

  test('QA-43 support gating', () {
    expect(shouldOfferSupport(ErrorCategory.temporaryNetwork), isFalse);
    expect(shouldOfferSupport(ErrorCategory.unexpected), isTrue);
    expect(shouldOfferSupport(ErrorCategory.authentication), isFalse);
  });
}
