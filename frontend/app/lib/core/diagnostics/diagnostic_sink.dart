import 'diagnostic_event.dart';

abstract interface class DiagnosticSink {
  void write(DiagnosticEvent event);
}

class NoopDiagnosticSink implements DiagnosticSink {
  const NoopDiagnosticSink();

  @override
  void write(DiagnosticEvent event) {}
}

class InMemoryDiagnosticSink implements DiagnosticSink {
  InMemoryDiagnosticSink({this.capacity = 100})
    : assert(capacity > 0, 'capacity must be positive');

  final int capacity;
  final List<DiagnosticEvent> _events = <DiagnosticEvent>[];

  List<DiagnosticEvent> get events => List.unmodifiable(_events);

  @override
  void write(DiagnosticEvent event) {
    if (_events.length == capacity) _events.removeAt(0);
    _events.add(event);
  }
}
