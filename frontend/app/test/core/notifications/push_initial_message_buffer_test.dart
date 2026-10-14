import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/notifications/push_messaging_service.dart';

/// The terminated-then-opened message must survive initialization.
///
/// `signals` is a broadcast stream, so a message read by `getInitialMessage()`
/// during `initialize()` would be published before any consumer can subscribe.
/// These tests pin the buffering contract that keeps it observable.
void main() {
  const first = PushMessageSignal(messageId: 'first', data: {'kind': 'a'});
  const second = PushMessageSignal(messageId: 'second');

  test('an empty buffer drains to nothing', () {
    expect(PushInitialSignalBuffer().drain(), isEmpty);
  });

  test('a buffered message is returned in arrival order', () {
    final buffer = PushInitialSignalBuffer()..add(first)..add(second);

    expect(buffer.drain(), [first, second]);
  });

  test('draining twice does not replay an already delivered message', () {
    final buffer = PushInitialSignalBuffer()..add(first);

    expect(buffer.drain(), [first]);
    expect(buffer.drain(), isEmpty);
    expect(buffer.isDrained, isTrue);
  });

  test('a buffer can be seeded with messages already collected', () {
    expect(PushInitialSignalBuffer([first]).drain(), [first]);
  });

  test('drained signals are not modifiable by the caller', () {
    final drained = PushInitialSignalBuffer([first]).drain();

    expect(() => drained.add(second), throwsUnsupportedError);
  });
}
