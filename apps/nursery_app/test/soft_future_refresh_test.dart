import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';

void main() {
  test('QA-40-M softReplaceFuture assigns only after await completes', () async {
    final events = <String>[];
    Object? assigned;

    final done = softReplaceFuture<int>(
      load: () async {
        events.add('load-start');
        await Future<void>.delayed(const Duration(milliseconds: 20));
        events.add('load-done');
        return 42;
      },
      onData: (data) {
        events.add('onData');
        assigned = data;
      },
    );

    events.add('after-call');
    await done;

    expect(events, ['load-start', 'after-call', 'load-done', 'onData']);
    expect(assigned, 42);
  });

  test('QA-40-M softReplaceFuture preserves prior data on error', () async {
    var value = 7;
    await softReplaceFuture<int>(
      load: () async {
        throw StateError('network');
      },
      onData: (data) => value = data,
      onError: (_) {},
    );
    expect(value, 7);
  });

  test('QA-40-M completedFuture is immediately done for FutureBuilder', () async {
    final f = completedFuture('ok');
    expect(f, isA<Future<String>>());
    expect(await f, 'ok');
  });
}
