import '../../config/app_config.dart';
import '../../config/app_environment.dart';
import 'console_diagnostic_sink.dart';
import 'diagnostic_event.dart';
import 'diagnostic_sink.dart';

abstract interface class AppDiagnostics {
  void record(DiagnosticEvent event);
}

class DefaultAppDiagnostics implements AppDiagnostics {
  const DefaultAppDiagnostics({required this.enabled, required this.sink});

  final bool enabled;
  final DiagnosticSink sink;

  @override
  void record(DiagnosticEvent event) {
    if (!enabled) return;
    try {
      sink.write(event);
    } catch (_) {
      // Diagnostics must never interrupt the operation being diagnosed.
    }
  }
}

class NoopAppDiagnostics extends DefaultAppDiagnostics {
  const NoopAppDiagnostics()
    : super(enabled: false, sink: const NoopDiagnosticSink());
}

abstract final class DiagnosticPolicy {
  static AppDiagnostics forConfig(AppConfig config, {DiagnosticSink? sink}) {
    final enabled =
        config.enableDiagnostics &&
        config.environment != AppEnvironment.production;
    return DefaultAppDiagnostics(
      enabled: enabled,
      sink:
          sink ??
          (enabled ? ConsoleDiagnosticSink() : const NoopDiagnosticSink()),
    );
  }

  static AppDiagnostics bootstrap() {
    final environment = AppEnvironment.tryParse(
      const String.fromEnvironment('APP_ENV'),
    );
    final enabled =
        const String.fromEnvironment(
          'ENABLE_DIAGNOSTICS',
        ).trim().toLowerCase() ==
        'true';
    if (environment == null) return const NoopAppDiagnostics();
    return forConfig(
      AppConfig(
        environment: environment,
        apiBaseUrl: 'http://diagnostics-bootstrap.invalid',
        enableDiagnostics: enabled,
      ),
    );
  }
}
