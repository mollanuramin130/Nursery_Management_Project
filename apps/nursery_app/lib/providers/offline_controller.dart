import 'package:flutter/foundation.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/data/mock_data_mode.dart';

/// QA-37/38 — mock mode, DEBUG simulation, sync generation, serving-local flag.
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

  MockDataMode get mode => _mode;
  NetworkSimulation get simulation => _simulation;
  DataSourceKind? get lastSource => _lastSource;
  bool get servingLocal => _servingLocal;
  bool get syncing => _syncing;

  /// Screens soft-refresh when this increments (reconnect / manual sync).
  int get syncGeneration => _syncGeneration;

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

  void markSource(DataSourceKind source) {
    _lastSource = source;
    final local = source != DataSourceKind.remote;
    if (_servingLocal == local && !_syncing) return;
    _servingLocal = local;
    notifyListeners();
  }

  void markRemoteOk() {
    final changed = _servingLocal || _lastSource != DataSourceKind.remote || _syncing;
    _lastSource = DataSourceKind.remote;
    _servingLocal = false;
    _syncing = false;
    if (!changed) return;
    notifyListeners();
  }

  void beginSync() {
    if (_syncing) return;
    _syncing = true;
    notifyListeners();
  }

  void endSync({required bool success}) {
    _syncing = false;
    if (success) {
      _servingLocal = false;
      _lastSource = DataSourceKind.remote;
    }
    notifyListeners();
  }

  /// Called when health probe / transport recovers.
  void notifyReconnected() {
    _syncGeneration++;
    _syncing = true;
    notifyListeners();
  }

  String? localBannerSuffix(NetworkKind kind) {
    if (_syncing) return 'Updating the latest information…';
    if (!_servingLocal) return null;
    if (kind == NetworkKind.offline) {
      return "You're offline · Showing saved GreenLeaf data";
    }
    return 'Showing your saved GreenLeaf data';
  }
}
