import 'dart:async';

import 'package:flutter/foundation.dart' hide DiagnosticLevel;
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/console_diagnostic_sink.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_category.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_code.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_error_boundary.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_event.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_level.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';

void main() {
  test('creates structured events with stable fields and timestamp', () {
    final event = DiagnosticEvent(
      level: DiagnosticLevel.warning,
      category: DiagnosticCategory.network,
      code: DiagnosticCode.apiRateLimited,
      timestamp: DateTime.utc(2026, 10, 9, 12),
      requestId: 'req_123',
      context: const DiagnosticContext(
        environment: AppEnvironment.local,
        operation: DiagnosticOperation.apiRequest,
        httpMethod: DiagnosticHttpMethod.get,
        statusCode: 429,
        apiErrorCode: 'RATE_LIMITED',
        retryAfterSeconds: 30,
      ),
    );

    expect(event.toJson(), {
      'timestamp': '2026-10-09T12:00:00.000Z',
      'level': 'warning',
      'category': 'network',
      'code': 'API_RATE_LIMITED',
      'request_id': 'req_123',
      'context': {
        'environment': 'local',
        'operation': 'apiRequest',
        'http_method': 'GET',
        'status_code': 429,
        'api_error_code': 'RATE_LIMITED',
        'retry_after_seconds': 30,
      },
    });
  });

  test('in-memory sink is bounded and exposes no mutable list', () {
    final sink = InMemoryDiagnosticSink(capacity: 2);
    for (var index = 0; index < 3; index++) {
      sink.write(
        DiagnosticEvent.now(
          level: DiagnosticLevel.debug,
          category: DiagnosticCategory.startup,
          code: DiagnosticCode.appStarted,
        ),
      );
    }

    expect(sink.events, hasLength(2));
    expect(() => sink.events.clear(), throwsUnsupportedError);
  });

  test('disabled diagnostics do not write to the sink', () {
    final sink = InMemoryDiagnosticSink();
    final diagnostics = DefaultAppDiagnostics(enabled: false, sink: sink);

    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.info,
        category: DiagnosticCategory.startup,
        code: DiagnosticCode.appStarted,
      ),
    );

    expect(sink.events, isEmpty);
  });

  test('sink failures are isolated from application operations', () {
    const diagnostics = DefaultAppDiagnostics(
      enabled: true,
      sink: _ThrowingSink(),
    );

    expect(
      () => diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.error,
          category: DiagnosticCategory.unexpected,
          code: DiagnosticCode.unhandledAsyncError,
        ),
      ),
      returnsNormally,
    );
  });

  test('production diagnostics remain disabled even when requested', () {
    final sink = InMemoryDiagnosticSink();
    final diagnostics = DiagnosticPolicy.forConfig(
      const AppConfig(
        environment: AppEnvironment.production,
        apiBaseUrl: 'https://api.example.test',
        enableDiagnostics: true,
      ),
      sink: sink,
    );

    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.info,
        category: DiagnosticCategory.startup,
        code: DiagnosticCode.appStarted,
      ),
    );

    expect(sink.events, isEmpty);
  });

  test(
    'console output is structured and excludes unsupported sensitive fields',
    () {
      final lines = <String>[];
      final sink = ConsoleDiagnosticSink(output: lines.add);

      sink.write(
        DiagnosticEvent.now(
          level: DiagnosticLevel.info,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.authSessionRenewalFailed,
        ),
      );

      expect(lines.single, contains('AUTH_SESSION_RENEWAL_FAILED'));
      expect(lines.single, isNot(contains('Authorization')));
      expect(lines.single, isNot(contains('session-token')));
      expect(lines.single, isNot(contains('email@example.com')));
    },
  );

  test('unsafe request IDs are rejected by the event boundary', () {
    expect(
      () => DiagnosticEvent.now(
        level: DiagnosticLevel.info,
        category: DiagnosticCategory.network,
        code: DiagnosticCode.apiRequestFailed,
        requestId: 'request id with spaces',
      ),
      throwsArgumentError,
    );
    expect(
      const DiagnosticContext(apiErrorCode: 'email@example.com').toJson(),
      isEmpty,
    );
    expect(
      () => DiagnosticEvent.now(
        level: DiagnosticLevel.info,
        category: DiagnosticCategory.network,
        code: DiagnosticCode.apiRequestFailed,
        requestId: 'x' * 129,
      ),
      throwsArgumentError,
    );
  });

  test('framework failures are captured once without recording raw errors', () {
    final sink = InMemoryDiagnosticSink();
    final diagnostics = DefaultAppDiagnostics(enabled: true, sink: sink);
    final boundary = DiagnosticErrorBoundary(
      diagnostics: diagnostics,
      previousFlutterError: (_) {},
    );
    final error = StateError('private exception text');
    final stack = StackTrace.current;
    final details = FlutterErrorDetails(exception: error, stack: stack);

    boundary.handleFlutterError(details);
    boundary.handleFlutterError(details);

    expect(sink.events, hasLength(1));
    expect(sink.events.single.code, DiagnosticCode.unexpectedFlutterError);
    expect(sink.events.single.toJson().toString(), isNot(contains('private')));
  });

  test('unhandled async failures are captured without recursive reporting', () {
    final sink = InMemoryDiagnosticSink();
    final diagnostics = DefaultAppDiagnostics(enabled: true, sink: sink);
    final boundary = DiagnosticErrorBoundary(diagnostics: diagnostics);
    final error = StateError('private async exception text');
    final stack = StackTrace.current;

    expect(boundary.handleAsyncError(error, stack), isFalse);
    expect(boundary.handleAsyncError(error, stack), isTrue);
    expect(sink.events, hasLength(1));
    expect(sink.events.single.code, DiagnosticCode.unhandledAsyncError);
  });

  test('zoned async failures are forwarded after recording', () {
    final sink = InMemoryDiagnosticSink();
    final diagnostics = DefaultAppDiagnostics(enabled: true, sink: sink);
    final boundary = DiagnosticErrorBoundary(diagnostics: diagnostics);
    final error = StateError('private async exception text');
    final stack = StackTrace.current;
    final forwarded = <Object>[];
    final parentZone = Zone.current.fork(
      specification: ZoneSpecification(
        handleUncaughtError: (_, _, _, error, _) => forwarded.add(error),
      ),
    );

    parentZone.run(() {
      boundary.handleZonedAsyncError(parentZone, error, stack);
    });

    expect(sink.events, hasLength(1));
    expect(sink.events.single.code, DiagnosticCode.unhandledAsyncError);
    expect(forwarded, contains(same(error)));
  });
}

class _ThrowingSink implements DiagnosticSink {
  const _ThrowingSink();

  @override
  void write(DiagnosticEvent event) => throw StateError('sink failure');
}
