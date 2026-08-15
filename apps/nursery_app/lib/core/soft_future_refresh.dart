import 'package:flutter/foundation.dart';

/// Soft-refresh helper for [FutureBuilder] screens (QA-40 Mobile).
///
/// Assigning a *new incomplete* [Future] to FutureBuilder forces
/// `ConnectionState.waiting` and replaces content with a skeleton.
/// Await the load first, then assign [SynchronousFuture] / [Future.value]
/// so existing UI stays visible and [RefreshIndicator] finishes correctly.
Future<void> softReplaceFuture<T>({
  required Future<T> Function() load,
  required void Function(T data) onData,
  void Function(Object error)? onError,
}) async {
  try {
    final data = await load();
    onData(data);
  } catch (e, st) {
    onError?.call(e);
    assert(() {
      debugPrint('softReplaceFuture error: $e\n$st');
      return true;
    }());
  }
}

/// Completed future for FutureBuilder without a waiting flash.
Future<T> completedFuture<T>(T data) => SynchronousFuture<T>(data);
