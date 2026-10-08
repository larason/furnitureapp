import 'package:flutter/foundation.dart' hide DiagnosticLevel;

import 'app_diagnostics.dart';
import 'diagnostic_category.dart';
import 'diagnostic_code.dart';
import 'diagnostic_event.dart';
import 'diagnostic_level.dart';

class DiagnosticErrorBoundary {
  DiagnosticErrorBoundary({
    required this.diagnostics,
    this.previousFlutterError,
    this.previousAsyncError,
  });

  final AppDiagnostics diagnostics;
  final FlutterExceptionHandler? previousFlutterError;
  final bool Function(Object, StackTrace)? previousAsyncError;
  final Set<String> _reported = <String>{};

  void handleFlutterError(FlutterErrorDetails details) {
    if (_wasReported(details.exception, details.stack ?? StackTrace.empty)) {
      return;
    }
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.critical,
        category: DiagnosticCategory.unexpected,
        code: DiagnosticCode.unexpectedFlutterError,
        context: const DiagnosticContext(
          operation: DiagnosticOperation.framework,
        ),
      ),
    );
    if (previousFlutterError != null) {
      previousFlutterError!(details);
    } else if (kDebugMode) {
      FlutterError.presentError(details);
    }
  }

  bool handleAsyncError(Object error, StackTrace stackTrace) {
    if (_wasReported(error, stackTrace)) return true;
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.critical,
        category: DiagnosticCategory.unexpected,
        code: DiagnosticCode.unhandledAsyncError,
        context: const DiagnosticContext(
          operation: DiagnosticOperation.framework,
        ),
      ),
    );
    return previousAsyncError?.call(error, stackTrace) ?? false;
  }

  bool _wasReported(Object error, StackTrace stackTrace) {
    final key = '${identityHashCode(error)}:${identityHashCode(stackTrace)}';
    if (_reported.contains(key)) return true;
    if (_reported.length == 128) _reported.remove(_reported.first);
    _reported.add(key);
    return false;
  }
}

DiagnosticErrorBoundary installDiagnosticErrorBoundary(
  AppDiagnostics diagnostics,
) {
  final boundary = DiagnosticErrorBoundary(
    diagnostics: diagnostics,
    previousFlutterError: FlutterError.onError,
    previousAsyncError: PlatformDispatcher.instance.onError,
  );
  FlutterError.onError = boundary.handleFlutterError;
  PlatformDispatcher.instance.onError = boundary.handleAsyncError;
  return boundary;
}
