import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/api_health.dart';
import 'package:nursery_app/core/network_errors.dart';

/// QA-37/38 — connectivity / API reachability + reconnect hook.
class NetworkStatusProvider extends ChangeNotifier {
  NetworkKind _kind = NetworkKind.online;
  String? _message;
  bool _probing = false;
  DateTime? _lastFailureAt;
  int _autoAttempts = 0;
  Timer? _retryTimer;
  bool _wasDegraded = false;

  /// Fired once when transitioning from degraded → healthy (QA-38 sync).
  void Function()? onReconnected;

  NetworkKind get kind => _kind;
  String? get message => _message;
  bool get isDegraded =>
      _kind != NetworkKind.online && _kind != NetworkKind.unknownError;
  /// Visible degraded network only — never "online" / "reconnecting" (QA-43).
  bool get showBanner =>
      _kind == NetworkKind.offline ||
      _kind == NetworkKind.apiUnavailable ||
      _kind == NetworkKind.apiTimeout ||
      _kind == NetworkKind.serverError;

  String get bannerText => _message ?? bannerCopy(_kind);

  void reportSuccess() {
    final recovered = _wasDegraded || showBanner || _lastFailureAt != null;
    _autoAttempts = 0;
    _retryTimer?.cancel();
    _wasDegraded = false;
    if (_kind == NetworkKind.online && _message == null && !recovered) return;
    _kind = NetworkKind.online;
    _message = null;
    notifyListeners();
    if (recovered) {
      onReconnected?.call();
    }
  }

  void reportFailure(ClassifiedNetworkError error) {
    _lastFailureAt = DateTime.now();
    _wasDegraded = true;
    if (error.kind == NetworkKind.authExpired ||
        error.kind == NetworkKind.unknownError &&
            error.statusCode != null &&
            error.statusCode! < 500 &&
            error.statusCode != 408 &&
            error.statusCode != 429) {
      return;
    }
    final kind = error.kind == NetworkKind.unknownError
        ? NetworkKind.apiUnavailable
        : error.kind;
    _kind = kind;
    _message = error.userMessage;
    notifyListeners();
    _scheduleAutoProbe();
  }

  Future<void> retryNow() async {
    if (_probing) return;
    _probing = true;
    // Keep the existing degraded banner; do not flash "reconnecting".
    final health = await probeApiHealth();
    _probing = false;
    if (health.ok) {
      reportSuccess();
    } else {
      _kind = NetworkKind.apiUnavailable;
      _message = 'Unable to connect to GreenLeaf right now.';
      notifyListeners();
      _scheduleAutoProbe();
    }
  }

  void onAppResumed() {
    if (!showBanner && _lastFailureAt == null) return;
    unawaited(retryNow());
  }

  void _scheduleAutoProbe() {
    _retryTimer?.cancel();
    if (_autoAttempts >= 3) return;
    final delaySec = 1 << _autoAttempts; // 1, 2, 4
    _autoAttempts++;
    _retryTimer = Timer(Duration(seconds: delaySec), () {
      unawaited(retryNow());
    });
  }

  @override
  void dispose() {
    _retryTimer?.cancel();
    super.dispose();
  }
}
