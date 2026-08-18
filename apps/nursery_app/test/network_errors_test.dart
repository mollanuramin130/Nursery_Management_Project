import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/network_errors.dart';

void main() {
  test('QA-37 classifyHttpStatus maps shopper-safe copy', () {
    expect(classifyHttpStatus(401).userMessage, contains('session'));
    expect(classifyHttpStatus(403).userMessage, contains('permission'));
    expect(classifyHttpStatus(429).kind, NetworkKind.rateLimited);
    expect(classifyHttpStatus(429).kind, isNot(NetworkKind.offline));
    expect(classifyHttpStatus(429).userMessage.toLowerCase(), isNot(contains('offline')));
    expect(classifyHttpStatus(503).kind, NetworkKind.apiUnavailable);
    expect(classifyHttpStatus(503).userMessage, contains('GreenLeaf'));
    expect(classifyHttpStatus(500).userMessage, contains('unavailable'));
    expect(classifyHttpStatus(500).kind, NetworkKind.serverError);
  });

  test('QA-37 timeout Dio maps to apiTimeout', () {
    final e = DioException(
      requestOptions: RequestOptions(path: '/x'),
      type: DioExceptionType.receiveTimeout,
    );
    final c = classifyDioException(e);
    expect(c.kind, NetworkKind.apiTimeout);
    expect(c.userMessage.toLowerCase(), contains('long'));
  });

  test('QA-37 isTransientTransportFailure true without response', () {
    final e = DioException(
      requestOptions: RequestOptions(path: '/auth/refresh'),
      type: DioExceptionType.connectionError,
    );
    expect(isTransientTransportFailure(e), isTrue);
  });

  test('QA-37 sanitize hides SocketException-style copy', () {
    final c = classifyHttpStatus(
      null,
      'SocketException: Failed host lookup',
    );
    expect(c.userMessage.toLowerCase(), contains('unavailable'));
  });

  test('real connection error is not confirmed offline', () {
    final e = DioException(
      requestOptions: RequestOptions(path: '/home'),
      type: DioExceptionType.connectionError,
      message: 'Failed host lookup: 10.0.2.2',
    );
    final c = classifyDioException(e);
    expect(c.kind, NetworkKind.apiUnavailable);
    expect(c.userMessage.toLowerCase(), isNot(contains("you're offline")));
  });

  test('DEBUG simulated offline still classifies as offline', () {
    final e = DioException(
      requestOptions: RequestOptions(path: '/home'),
      type: DioExceptionType.connectionError,
      message: 'Failed host lookup (simulated)',
    );
    expect(classifyDioException(e).kind, NetworkKind.offline);
  });

  test('healthy API does not keep a leftover saved-data banner', () {
    expect(
      shouldShowNetworkBanner(
        kind: NetworkKind.online,
        showBanner: false,
        servingLocal: true,
      ),
      isFalse,
    );
    expect(
      shouldShowNetworkBanner(
        kind: NetworkKind.apiUnavailable,
        showBanner: true,
        servingLocal: true,
      ),
      isTrue,
    );
  });
}
