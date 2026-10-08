import 'dart:async';

class RequestCancellation {
  final Completer<void> _completer = Completer<void>();

  Future<void> get future => _completer.future;

  bool get isCancelled => _completer.isCompleted;

  void cancel() {
    if (!_completer.isCompleted) {
      _completer.complete();
    }
  }
}
