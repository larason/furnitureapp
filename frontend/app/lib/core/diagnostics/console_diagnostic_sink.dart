import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'diagnostic_event.dart';
import 'diagnostic_sink.dart';

class ConsoleDiagnosticSink implements DiagnosticSink {
  ConsoleDiagnosticSink({void Function(String)? output})
    : _output = output ?? debugPrint;

  final void Function(String) _output;

  @override
  void write(DiagnosticEvent event) {
    _output(jsonEncode(event.toJson()));
  }
}
