/// UPI checkout helpers (QA-21). Server remains authoritative for PAID.
library;

enum UpiUiStatus { pending, polling, paid, failed, expired, cancelled }

UpiUiStatus mapPaymentApiStatus(String? paymentStatus) {
  final s = (paymentStatus ?? '').toLowerCase();
  if (s == 'success' || s == 'paid' || s == 'captured') return UpiUiStatus.paid;
  if (s == 'failed') return UpiUiStatus.failed;
  if (s == 'cancelled' || s == 'canceled') return UpiUiStatus.cancelled;
  if (s == 'expired') return UpiUiStatus.expired;
  return UpiUiStatus.pending;
}

bool shouldContinueUpiPoll({
  required UpiUiStatus status,
  required DateTime startedAt,
  required DateTime now,
  Duration max = const Duration(minutes: 3),
}) {
  if (status == UpiUiStatus.paid ||
      status == UpiUiStatus.failed ||
      status == UpiUiStatus.expired ||
      status == UpiUiStatus.cancelled) {
    return false;
  }
  return now.difference(startedAt) < max;
}

/// QA-30 — only poll after a successful external UPI Intent launch.
/// If launch fails, checkout should fall back to Razorpay Checkout.
bool shouldPollAfterUpiIntentLaunch(bool launched) => launched;

String upiStatusLabel(UpiUiStatus status) {
  switch (status) {
    case UpiUiStatus.paid:
      return 'Paid';
    case UpiUiStatus.failed:
      return 'Failed';
    case UpiUiStatus.expired:
      return 'Expired';
    case UpiUiStatus.cancelled:
      return 'Cancelled';
    case UpiUiStatus.polling:
      return 'Waiting for payment…';
    case UpiUiStatus.pending:
      return 'Pending';
  }
}
