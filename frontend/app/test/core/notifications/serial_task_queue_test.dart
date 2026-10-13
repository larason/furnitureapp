import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/notifications/serial_task_queue.dart';

void main() {
  test('runs one item at a time, in arrival order', () async {
    final queue = SerialTaskQueue();
    final trace = <String>[];

    final first = queue.add(() async {
      trace.add('a:start');
      await Future<void>.delayed(const Duration(milliseconds: 20));
      trace.add('a:end');
    });
    final second = queue.add(() async {
      trace.add('b:start');
      trace.add('b:end');
    });

    await Future.wait([first, second]);

    expect(trace, ['a:start', 'a:end', 'b:start', 'b:end']);
  });

  test('never overlaps two operations', () async {
    final queue = SerialTaskQueue();
    var running = 0;
    var maxConcurrent = 0;

    for (var i = 0; i < 8; i++) {
      unawaited(
        queue.add(() async {
          running++;
          maxConcurrent = running > maxConcurrent ? running : maxConcurrent;
          await Future<void>.delayed(const Duration(milliseconds: 1));
          running--;
        }),
      );
    }
    await Future<void>.delayed(const Duration(milliseconds: 60));

    expect(maxConcurrent, 1);
  });

  test('a failing operation does not wedge the queue', () async {
    final queue = SerialTaskQueue();
    final ran = <String>[];

    await expectLater(
      queue.add(() async => throw StateError('boom')),
      throwsStateError,
    );
    await queue.add(() async => ran.add('after-failure'));

    expect(ran, ['after-failure']);
  });

  test('an operation that throws synchronously still runs', () async {
    final queue = SerialTaskQueue();
    final ran = <String>[];

    final failure = expectLater(
      queue.add(() => throw StateError('sync boom')),
      throwsStateError,
    );
    await queue.add(() async => ran.add('next'));
    await failure;

    expect(ran, ['next']);
  });

  test('an empty queue runs the next operation immediately', () async {
    final queue = SerialTaskQueue();
    final ran = <String>[];

    await queue.add(() async => ran.add('first'));

    expect(ran, ['first']);
  });
}
