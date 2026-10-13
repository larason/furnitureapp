/// Runs asynchronous work one item at a time, in arrival order.
///
/// Used where events must not overlap. Session changes are the case that
/// matters: a sign-out that runs while a sign-in registration is still in
/// flight would find no device to retire, and the registration would then
/// complete and leave the device registered to an account that has left.
final class SerialTaskQueue {
  Future<void> _tail = Future<void>.value();

  /// Enqueues [operation], starting it only once every previously enqueued
  /// operation has finished.
  ///
  /// The returned future completes with that operation's result. A failing
  /// operation is reported to the caller, but it does not break the chain: the
  /// next item still runs, so one failure cannot wedge the queue permanently.
  Future<T> add<T>(Future<T> Function() operation) {
    final result = _tail.then<T>((_) => operation());

    _tail = result.then<void>((_) {}, onError: (Object _, StackTrace _) {});

    return result;
  }
}
