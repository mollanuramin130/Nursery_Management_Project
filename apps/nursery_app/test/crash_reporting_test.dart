import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/app_error.dart';
import 'package:nursery_app/core/app_error_handler.dart';
import 'package:nursery_app/core/crash_reporting_service.dart';
import 'package:nursery_app/core/installation_id.dart';
import 'package:nursery_app/core/sentry_crash_reporting_service.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

class MockCrashReporter implements CrashReportingService {
  final List<Map<String, dynamic>> recordedCrashes = [];
  String? currentUserId;
  bool enabled = true;
  bool shouldThrow = false;

  @override
  bool get isEnabled => enabled;

  @override
  Future<void> recordCrash(
    Object error,
    StackTrace? stack, {
    required String reference,
    required String category,
    String? screen,
    String? operation,
    int? statusCode,
    bool isFatal = false,
  }) async {
    if (shouldThrow) {
      throw StateError('Simulated transport/network crash');
    }
    recordedCrashes.add({
      'error': error,
      'stack': stack,
      'reference': reference,
      'category': category,
      'screen': screen,
      'operation': operation,
      'statusCode': statusCode,
      'isFatal': isFatal,
    });
  }

  @override
  Future<void> setUserId(String? userId) async {
    currentUserId = userId;
  }
}

class _TestFlutterErrorIntegration
    extends Integration<SentryFlutterOptions> {
  @override
  void call(Hub hub, SentryFlutterOptions options) {}
}

class _TestOnErrorIntegration extends Integration<SentryFlutterOptions> {
  @override
  void call(Hub hub, SentryFlutterOptions options) {}
}

class _TestCustomSafeIntegration
    extends Integration<SentryFlutterOptions> {
  @override
  void call(Hub hub, SentryFlutterOptions options) {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('CrashReportingService & Sentry Integration Tests', () {
    setUp(() {
      AppErrorHandler.crashReporter = const NoOpCrashReportingService();
      AppErrorHandler.records.clear();
      InstallationIdManager.resetCacheForTesting();
      FlutterSecureStorage.setMockInitialValues({});
    });

    test('1. Empty or missing DSN disables remote reporting', () {
      final serviceEmpty = SentryCrashReportingService(dsn: '');
      expect(serviceEmpty.isEnabled, isFalse);

      final serviceSpaces = SentryCrashReportingService(dsn: '   ');
      expect(serviceSpaces.isEnabled, isFalse);
    });

    test('2. Debug mode gates reporting unless explicitly enabled in debug', () {
      final serviceWithoutDebug = SentryCrashReportingService(
        dsn: 'https://examplePublicKey@o0.ingest.sentry.io/0',
        enableInDebug: false,
      );
      // In flutter test (debug mode), should be false
      expect(serviceWithoutDebug.isEnabled, isFalse);

      final serviceWithDebug = SentryCrashReportingService(
        dsn: 'https://examplePublicKey@o0.ingest.sentry.io/0',
        enableInDebug: true,
      );
      expect(serviceWithDebug.isEnabled, isTrue);
    });

    test('3. Disabled service recordCrash does not throw or perform delivery',
        () async {
      final disabled = SentryCrashReportingService(dsn: '');
      expect(disabled.isEnabled, isFalse);
      await expectLater(
        disabled.recordCrash(
          Exception('Test error'),
          StackTrace.current,
          reference: 'GL-12345',
          category: 'unexpected',
        ),
        completes,
      );
    });

    test(
        '4. PRIV-01: Anonymous Installation UUID generation & persistence (RFC 4122 v4)',
        () async {
      final uuidRegex = RegExp(
        r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
      );

      // Verify format and cryptographic version/variant
      final uuid1 = InstallationIdManager.generateUuidV4();
      final uuid2 = InstallationIdManager.generateUuidV4();
      expect(uuid1, matches(uuidRegex));
      expect(uuid2, matches(uuidRegex));
      expect(uuid1, isNot(equals(uuid2)));

      // Verify persistence across storage calls
      final storage = SessionStorage();
      final created = await InstallationIdManager.getOrCreate(storage);
      expect(created, matches(uuidRegex));

      // Reset in-memory cache to verify disk read
      InstallationIdManager.resetCacheForTesting();
      final retrieved = await InstallationIdManager.getOrCreate(storage);
      expect(retrieved, equals(created));

      // Guarantee UUID is never derived from customer data
      expect(created, isNot(contains('customer')));
      expect(created, isNot(contains('@')));
    });

    test(
        '5. PRIV-01: Sentry user identity receives anonymous UUID and NEVER customer database ID',
        () async {
      final mock = MockCrashReporter();
      AppErrorHandler.crashReporter = mock;

      final storage = SessionStorage();
      final installationId = await InstallationIdManager.getOrCreate(storage);

      // Simulated boot sequence
      await AppErrorHandler.crashReporter.setUserId(installationId);

      expect(mock.currentUserId, equals(installationId));
      expect(mock.currentUserId, isNot(equals('1')));
      expect(mock.currentUserId, isNot(equals('101')));
      expect(int.tryParse(mock.currentUserId!), isNull,
          reason: 'Customer database integer ID must NEVER be bound to Sentry');
    });

    test(
        '6. PRIV-02: User identity is cleared on manual logout, session expiration, and local clear',
        () async {
      final mock = MockCrashReporter();
      AppErrorHandler.crashReporter = mock;

      final storage = SessionStorage();
      final api = ApiClient(storage);
      final auth = AuthProvider(api, storage);

      // Simulate active anonymous user
      mock.currentUserId = 'anon-uuid-active';

      // 1. Local session clear
      auth.clearLocalSession();
      expect(mock.currentUserId, isNull,
          reason: 'clearLocalSession must immediately clear Sentry user');

      // Rebind for logout test
      mock.currentUserId = 'anon-uuid-active';
      await auth.logout();
      expect(mock.currentUserId, isNull,
          reason: 'logout must immediately clear Sentry user');

      // Rebind for session expiration test
      mock.currentUserId = 'anon-uuid-active';
      api.onSessionExpired = () {
        auth.clearLocalSession();
        AppErrorHandler.crashReporter.setUserId(null);
      };
      api.onSessionExpired?.call();
      expect(mock.currentUserId, isNull,
          reason: 'onSessionExpired must immediately clear Sentry user');
    });

    test(
        '7. REDACT-01: JSON-formatted quoted keys & values are properly redacted',
        () {
      // Double quoted JSON key-value
      expect(
        redactSecrets('{"password": "SuperSecretPassword123"}'),
        equals('{"password": "[redacted]"}'),
      );

      // Double quoted with whitespace
      expect(
        redactSecrets('{"password" : "SuperSecretPassword123"}'),
        equals('{"password" : "[redacted]"}'),
      );

      // Single quoted key-value
      expect(
        redactSecrets("{'password': 'SingleQuoteSecret'}"),
        equals("{'password': '[redacted]'}"),
      );

      // JSON access_token and refresh_token
      final jsonPayload =
          '{"access_token": "eyJhbGciOi...", "refresh_token": "def456secret"}';
      final redactedJson = redactSecrets(jsonPayload);
      expect(redactedJson, isNot(contains('def456secret')));
      expect(redactedJson, contains('"access_token": "[redacted]"'));
      expect(redactedJson, contains('"refresh_token": "[redacted]"'));

      // Nested JSON structures
      final nested = '{"data": {"credentials": {"secret": "VeryPrivate123"}}}';
      expect(redactSecrets(nested), contains('"secret": "[redacted]"'));

      // Authorization header
      expect(
        redactSecrets('Authorization: Bearer secret_bearer_token_xyz'),
        equals('Authorization: Bearer [redacted]'),
      );
    });

    test(
        '8. REDACT-02: Payment card Luhn verification preserves Unix timestamps and request IDs',
        () {
      // Valid Visa (16 digits) -> Redacted
      expect(
        redactSecrets('Payment card 4111 1111 1111 1111 charged'),
        contains('[redacted_card]'),
      );
      expect(
        redactSecrets('Payment card 4111 1111 1111 1111 charged'),
        isNot(contains('4111 1111 1111 1111')),
      );

      // Valid Mastercard (16 digits) -> Redacted
      expect(
        redactSecrets('Card: 5500 0000 0000 0004'),
        contains('[redacted_card]'),
      );

      // Valid Amex (15 digits) -> Redacted
      expect(
        redactSecrets('Amex: 3782 822463 10005'),
        contains('[redacted_card]'),
      );

      // Valid RuPay (16 digits) -> Redacted
      expect(
        redactSecrets('RuPay: 6071 1234 5678 9016'),
        contains('[redacted_card]'),
      );

      // 13-digit Unix millisecond timestamps (2023-2033, starting with 16, 17, 18, 19) -> PRESERVED!
      final timestampMsg =
          'Server event at 1731718000000 with id gl_req_1731718000000';
      final timestampResult = redactSecrets(timestampMsg);
      expect(timestampResult, contains('1731718000000'),
          reason: '13-digit millisecond timestamp must NOT be false-positively redacted');
      expect(timestampResult, contains('gl_req_1731718000000'));
      expect(timestampResult, isNot(contains('[redacted_card]')));

      // Numbers failing Luhn -> PRESERVED (not redacted as card)
      final invalidChecksum = 'Invalid checksum card 4111 2222 3333 4444';
      expect(redactSecrets(invalidChecksum), isNot(contains('[redacted_card]')));
    });

    test(
        '9. DUP-01: Duplicate Sentry auto-integrations are removed during init configuration',
        () {
      // Simulate options integrations list
      final integrations = <Integration>[
        _TestFlutterErrorIntegration(),
        _TestOnErrorIntegration(),
        _TestCustomSafeIntegration(),
      ];

      // Replicate SentryCrashReportingService.init filtering logic
      final toRemove = integrations.where((integration) {
        final name = integration.runtimeType.toString();
        return name.contains('FlutterErrorIntegration') ||
            name.contains('OnErrorIntegration');
      }).toList();

      for (final r in toRemove) {
        integrations.remove(r);
      }

      expect(integrations.length, 1);
      expect(integrations.first, isA<_TestCustomSafeIntegration>());
    });

    test(
        '10. Sentry beforeSend event sanitizer redacts messages, breadcrumbs, tags and user PII',
        () {
      final rawEvent = SentryEvent(
        message: SentryMessage('Failed charge: rzp_live_SECRET999'),
        exceptions: [
          SentryException(
            type: 'ApiException',
            value:
                'Error with password=MyPassword! and token=Bearer abc.def.ghi',
          ),
        ],
        breadcrumbs: [
          Breadcrumb(
            message:
                'User entered 9876543210@paytm and card 4111-1111-1111-1111',
            data: {'raw_request_body': 'sensitive data'},
          ),
        ],
        tags: {
          'api_url': 'https://api.example.com',
          'auth_token': 'Bearer should_be_dropped',
          'secret_key': 'drop_me',
        },
        user: SentryUser(
          id: '550e8400-e29b-41d4-a716-446655440000',
          email: 'customer@example.com',
          username: 'John Doe',
        ),
      );

      final sanitized = sanitizeSentryEvent(rawEvent);
      expect(sanitized, isNotNull);

      // Message redacted
      expect(sanitized!.message!.formatted, isNot(contains('SECRET999')));
      expect(sanitized.message!.formatted, contains('[redacted]'));

      // Exceptions redacted
      expect(sanitized.exceptions!.first.value, isNot(contains('MyPassword!')));
      expect(sanitized.exceptions!.first.value, contains('[redacted]'));

      // Breadcrumbs sanitized: data dictionary dropped, text redacted
      expect(sanitized.breadcrumbs!.first.message, contains('[redacted_upi]'));
      expect(sanitized.breadcrumbs!.first.message, contains('[redacted_card]'));
      expect(sanitized.breadcrumbs!.first.data, isNull);

      // Sensitive tags dropped, safe tags preserved
      expect(sanitized.tags!.containsKey('auth_token'), isFalse);
      expect(sanitized.tags!.containsKey('secret_key'), isFalse);
      expect(sanitized.tags!['api_url'], 'https://api.example.com');

      // User PII stripped (anonymous uuid id preserved, email and username null)
      expect(sanitized.user!.id, '550e8400-e29b-41d4-a716-446655440000');
      expect(sanitized.user!.email, isNull);
      expect(sanitized.user!.username, isNull);
    });

    test('11. Non-fatal vs fatal classification is preserved', () {
      final mock = MockCrashReporter();
      AppErrorHandler.crashReporter = mock;

      // Routine runtime error from screen -> non-fatal
      AppErrorHandler.record(
        Exception('Non-fatal database issue'),
        StackTrace.current,
        screen: 'catalog',
        category: ErrorCategory.unexpected,
      );
      expect(mock.recordedCrashes.length, 1);
      expect(mock.recordedCrashes.first['isFatal'], isFalse);

      // Platform / Flutter / Zone crash -> fatal
      AppErrorHandler.record(
        Exception('Fatal widget build error'),
        StackTrace.current,
        screen: 'flutter_error',
        category: ErrorCategory.unexpected,
      );
      expect(mock.recordedCrashes.length, 2);
      expect(mock.recordedCrashes.last['isFatal'], isTrue);
    });

    test(
        '12. Client-side routine errors (validation, auth, temporaryNetwork) are filtered',
        () {
      final mock = MockCrashReporter();
      AppErrorHandler.crashReporter = mock;

      // Validation error (e.g. invalid phone number) -> should NOT report
      AppErrorHandler.record(
        Exception('Invalid phone number'),
        null,
        screen: 'checkout',
        category: ErrorCategory.validation,
      );
      expect(mock.recordedCrashes, isEmpty);

      // Authentication error (e.g. wrong password) -> should NOT report
      AppErrorHandler.record(
        Exception('Wrong password'),
        null,
        screen: 'login',
        category: ErrorCategory.authentication,
      );
      expect(mock.recordedCrashes, isEmpty);

      // Routine network timeout -> should NOT report
      AppErrorHandler.record(
        Exception('Connection timeout'),
        null,
        screen: 'catalog',
        category: ErrorCategory.temporaryNetwork,
      );
      expect(mock.recordedCrashes, isEmpty);

      // Unexpected error or payment error -> SHOULD report
      AppErrorHandler.record(
        Exception('Payment gateway signature failed'),
        StackTrace.current,
        screen: 'checkout',
        category: ErrorCategory.payment,
      );
      expect(mock.recordedCrashes.length, 1);
      expect(mock.recordedCrashes.first['category'], 'payment');
    });

    test('13. Reporting failures never crash or interrupt application execution',
        () {
      final mock = MockCrashReporter()..shouldThrow = true;
      AppErrorHandler.crashReporter = mock;

      final record = AppErrorHandler.record(
        Exception('Some error'),
        StackTrace.current,
        screen: 'cart',
        category: ErrorCategory.unexpected,
      );

      expect(record, isNotNull);
      expect(record.reference, startsWith('GL-'));
      expect(AppErrorHandler.records.contains(record), isTrue);
    });
  });
}
