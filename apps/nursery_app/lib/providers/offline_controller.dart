import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/mock_data_mode.dart';

/// QA-37/38/42A — mock mode, DEBUG simulation, sync generation, degraded flag.
///
/// [servingLocal] means the UI is genuinely browsing cache/mock because fresh
/// remote data is unavailable — NOT merely because a cache peek ran during
/// stale-while-revalidate refresh.
class OfflineController extends ChangeNotifier {
  OfflineController({
    MockDataMode mode = MockDataMode.auto,
    NetworkSimulation simulation = NetworkSimulation.off,
  }) : _mode = mode,
       _simulation = simulation;

  MockDataMode _mode;
  NetworkSimulation _simulation;
  DataSourceKind? _lastSource;
  bool _servingLocal = false;
  bool _syncing = false;
  int _syncGeneration = 0;
  /// Recovery is silent (QA-43). Kept false so older callers stay no-op.
  bool _showBackOnline = false;

  MockDataMode get mode => _mode;
  NetworkSimulation get simulation => _simulation;
  DataSourceKind? get lastSource => _lastSource;
  bool get servingLocal => _servingLocal;
  bool get syncing => _syncing;
  bool get showBackOnline => _showBackOnline;

  /// Screens soft-refresh when this increments (reconnect / manual sync).
  int get syncGeneration => _syncGeneration;

  /// QA-41: bump without marking syncing (Retry / image revalidation).
  void bumpSyncGeneration() {
    _syncGeneration++;
    notifyListeners();
  }

  bool get forceTransportFailure =>
      _mode == MockDataMode.offlineSimulation ||
      _simulation == NetworkSimulation.offline ||
      _simulation == NetworkSimulation.timeout ||
      _simulation == NetworkSimulation.apiError;

  void setMode(MockDataMode next) {
    if (_mode == next) return;
    _mode = next;
    notifyListeners();
  }

  void setSimulation(NetworkSimulation next) {
    final prev = _simulation;
    if (prev == next) return;
    // reconnect chip → clear failure sim and request soft sync.
    if (next == NetworkSimulation.reconnect) {
      _simulation = NetworkSimulation.off;
      notifyListeners();
      notifyReconnected();
      return;
    }
    _simulation = next;
    notifyListeners();
  }

  /// Record data origin. Cache/mock peeks during SWR must pass [asDegraded]: false
  /// so the offline banner does not flash on every pull-to-refresh (QA-42A).
  void markSource(DataSourceKind source, {bool asDegraded = false}) {
    _lastSource = source;
    if (source == DataSourceKind.remote) {
      final changed = _servingLocal || _syncing || _showBackOnline;
      _servingLocal = false;
      _syncing = false;
      if (changed) notifyListeners();
      return;
    }
    // Quiet SWR peek / soft refresh — keep prior degraded flag unchanged.
    if (!asDegraded) return;
    if (_servingLocal) return;
    _servingLocal = true;
    notifyListeners();
  }

  /// Explicitly enter degraded browsing (API failed, offline sim, mock-only).
  void markDegraded(DataSourceKind source) {
    markSource(source, asDegraded: true);
  }

  void markRemoteOk() {
    final changed = _servingLocal || _syncing || _showBackOnline;
    _lastSource = DataSourceKind.remote;
    _servingLocal = false;
    _syncing = false;
    _showBackOnline = false;
    if (changed) notifyListeners();
  }

  void beginSync() {
    if (_syncing) return;
    _syncing = true;
    notifyListeners();
  }

  void endSync({required bool success}) {
    final wasSyncing = _syncing;
    _syncing = false;
    if (success) {
      final changed = wasSyncing || _servingLocal || _showBackOnline;
      _servingLocal = false;
      _showBackOnline = false;
      _lastSource = DataSourceKind.remote;
      if (changed) notifyListeners();
      return;
    }
    if (wasSyncing) notifyListeners();
  }

  /// Called when health probe / transport recovers.
  void notifyReconnected() {
    _syncGeneration++;
    _syncing = true;
    notifyListeners();
  }

  /// Compact copy only when genuinely serving local after an API failure (QA-43).
  String? localBannerSuffix(NetworkKind kind) {
    if (!_servingLocal) return null;
    return "You're offline · Showing saved data";
  }
}
