import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

class RazorpayCheckoutResult {
  RazorpayCheckoutResult({
    required this.paymentId,
    required this.orderId,
    required this.signature,
  });

  final String paymentId;
  final String orderId;
  final String signature;
}

class RazorpayCheckoutCancelled implements Exception {
  @override
  String toString() => 'Payment cancelled';
}

class RazorpayCheckoutFailed implements Exception {
  RazorpayCheckoutFailed(this.message);
  final String message;

  @override
  String toString() => message;
}

/// Opens Razorpay Checkout SDK and returns gateway identifiers for server verify.
Future<RazorpayCheckoutResult> openRazorpayCheckout({
  required Map<String, dynamic> clientPayload,
  required String description,
}) async {
  final mode = clientPayload['mode']?.toString();
  final orderId = clientPayload['order_id']?.toString();
  if (orderId == null || orderId.isEmpty) {
    throw RazorpayCheckoutFailed('Missing payment order id');
  }

  // Dev stub when API has no Razorpay keys — mirrors website local_stub path.
  // Must never run in release builds (production API must also refuse stub mode).
  if (mode == 'local_stub') {
    if (kReleaseMode) {
      throw RazorpayCheckoutFailed(
        'Online payments are not configured. Please try again later or use cash on delivery.',
      );
    }
    final paymentId =
        'local_${DateTime.now().millisecondsSinceEpoch}_${orderId.hashCode.abs()}';
    return RazorpayCheckoutResult(
      paymentId: paymentId,
      orderId: orderId,
      signature: 'local_$orderId',
    );
  }

  final key = clientPayload['key']?.toString();
  final amount = clientPayload['amount'];
  final currency = clientPayload['currency']?.toString() ?? 'INR';
  if (key == null || key.isEmpty || amount == null) {
    throw RazorpayCheckoutFailed('Incomplete payment configuration');
  }

  final prefill = clientPayload['prefill'];
  final options = <String, dynamic>{
    'key': key,
    'amount': amount,
    'currency': currency,
    'name': clientPayload['name'] ?? 'GreenLeaf Nursery',
    'description': description,
    'order_id': orderId,
    if (prefill is Map) 'prefill': Map<String, dynamic>.from(prefill),
    'theme': {'color': '#1F6B4A'},
  };

  final razorpay = Razorpay();
  final completer = Completer<RazorpayCheckoutResult>();

  void clear() {
    razorpay.clear();
  }

  razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (PaymentSuccessResponse res) {
    if (!completer.isCompleted) {
      completer.complete(
        RazorpayCheckoutResult(
          paymentId: res.paymentId ?? '',
          orderId: res.orderId ?? orderId,
          signature: res.signature ?? '',
        ),
      );
    }
    clear();
  });
  razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (PaymentFailureResponse res) {
    if (!completer.isCompleted) {
      final msg = (res.message ?? 'Payment failed').toLowerCase();
      final cancelled =
          msg.contains('cancel') ||
          msg.contains('dismiss') ||
          res.code == 2; // common Razorpay cancel code
      if (cancelled) {
        completer.completeError(RazorpayCheckoutCancelled());
      } else {
        completer.completeError(
          RazorpayCheckoutFailed(res.message ?? 'Payment failed'),
        );
      }
    }
    clear();
  });
  razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});

  try {
    razorpay.open(options);
    return await completer.future;
  } catch (e) {
    clear();
    rethrow;
  }
}
