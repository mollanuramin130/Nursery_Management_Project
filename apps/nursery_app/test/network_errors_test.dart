import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/network_errors.dart';

void main() {
  test('QA-37 classifyHttpStatus maps shopper-safe copy', () {
    expect(classifyHttpStatus(401).userMessage, contains('session'));
    expect(classifyHttpStatus(403).userMessage, contains('permission'));
    expect(classifyHttpStatus(429).kind, NetworkKind.rateLimited);
    expect(classifyHttpStatus(503).kind, NetworkKind.apiUnavailable);
    expect(classifyHttpStatus(503).userMessage, contains('temporarily unavailable'));
    expect(classifyHttpStatus(500).userMessage, contains('our side'));
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
    expect(c.userMessage.toLowerCase(), contains('offline'));
  });
}
