import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/app_error.dart';
import 'package:nursery_app/core/app_error_handler.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/core/support.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/providers/offline_controller.dart';

void main() {
  test('QA-43 helpline and reference format', () {
    expect(kGreenLeafSupportPhone, '8926627220');
    expect(supportTelUri().toString(), 'tel:8926627220');
    var n = 0;
    final ref = newErrorReference(() => n++);
    expect(looksLikeErrorReference(ref), isTrue);
    expect(ref, startsWith('GL-'));
  });

  test('QA-43 support offered only for unexpected / persistent / payment', () {
    expect(shouldOfferSupport(ErrorCategory.temporaryNetwork), isFalse);
    expect(shouldOfferSupport(ErrorCategory.authentication), isFalse);
    expect(shouldOfferSupport(ErrorCategory.validation), isFalse);
    expect(shouldOfferSupport(ErrorCategory.apiUnavailable), isFalse);
    expect(
      shouldOfferSupport(ErrorCategory.apiUnavailable, retryCount: 2),
      isTrue,
    );
    expect(shouldOfferSupport(ErrorCategory.unexpected), isTrue);
    expect(shouldOfferSupport(ErrorCategory.payment), isTrue);
    expect(
      shouldOfferSupport(ErrorCategory.temporaryNetwork, userRequested: true),
      isTrue,
    );
  });

  test('QA-43 HTTP 500 is server error, not offline', () {
    final c = classifyHttpStatus(500);
    expect(c.kind, NetworkKind.serverError);
    expect(c.userMessage.toLowerCase(), isNot(contains('offline')));
    expect(c.userMessage.toLowerCase(), contains('unavailable'));
  });

  test('QA-43 HTTP 503 is unavailable, not offline', () {
    final c = classifyHttpStatus(503);
    expect(c.kind, NetworkKind.apiUnavailable);
    expect(c.userMessage.toLowerCase(), isNot(contains('offline')));
  });

  test('QA-43 secrets and stacks never reach user copy', () {
    expect(sanitizeUserFacing('password=Secret@123'), isNot(contains('Secret')));
    expect(
      redactSecrets('Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.aaa.bbb'),
      contains('[redacted]'),
    );
    expect(sanitizeUserFacing('SQLSTATE[HY000]'), isNot(contains('SQL')));
    expect(sanitizeUserFacing('rzp_test_ABC123xyz'), isNot(contains('rzp_')));
  });

  test('QA-43 banner copy: 5xx ≠ you are offline', () {
    expect(
      statusBannerText(kind: NetworkKind.serverError, servingLocal: true)
          .toLowerCase(),
      isNot(contains("you're offline")),
    );
    expect(
      statusBannerText(kind: NetworkKind.apiUnavailable, servingLocal: true),
      contains('saved data'),
    );
    expect(
      statusBannerText(kind: NetworkKind.offline, servingLocal: true),
      contains('offline'),
    );
  });

  test('QA-43 cache peek still has no banner (QA-42A)', () {
    final offline = OfflineController();
    offline.markSource(DataSourceKind.cache);
    expect(offline.servingLocal, isFalse);
    expect(offline.localBannerSuffix(NetworkKind.online), isNull);
  });
}
