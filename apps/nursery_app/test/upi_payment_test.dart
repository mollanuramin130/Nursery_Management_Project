import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/services/upi_payment.dart';

void main() {
  test('mapPaymentApiStatus maps success to paid', () {
    expect(mapPaymentApiStatus('success'), UpiUiStatus.paid);
    expect(mapPaymentApiStatus('failed'), UpiUiStatus.failed);
    expect(mapPaymentApiStatus('pending'), UpiUiStatus.pending);
  });

  test('shouldContinueUpiPoll stops on terminal or timeout', () {
    final start = DateTime.fromMillisecondsSinceEpoch(0);
    expect(
      shouldContinueUpiPoll(
        status: UpiUiStatus.pending,
        startedAt: start,
        now: start.add(const Duration(seconds: 1)),
      ),
      isTrue,
    );
    expect(
      shouldContinueUpiPoll(
        status: UpiUiStatus.paid,
        startedAt: start,
        now: start.add(const Duration(seconds: 1)),
      ),
      isFalse,
    );
    expect(
      shouldContinueUpiPoll(
        status: UpiUiStatus.pending,
        startedAt: start,
        now: start.add(const Duration(minutes: 5)),
        max: const Duration(minutes: 3),
      ),
      isFalse,
    );
  });

  test('shouldPollAfterUpiIntentLaunch gates Checkout fallback', () {
    expect(shouldPollAfterUpiIntentLaunch(true), isTrue);
    expect(shouldPollAfterUpiIntentLaunch(false), isFalse);
  });

  test('upiStatusLabel is human readable', () {
    expect(upiStatusLabel(UpiUiStatus.polling), contains('Waiting'));
  });
}
