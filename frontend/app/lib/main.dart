import 'dart:io';
import 'dart:async';

import 'package:flutter/foundation.dart' hide DiagnosticLevel;
import 'package:flutter/material.dart' hide DiagnosticLevel;
import 'package:marionette_flutter/marionette_flutter.dart';

import 'app.dart';
import 'config/config_validation.dart';
import 'core/auth/clerk_auth_adapter.dart';
import 'core/diagnostics/app_diagnostics.dart';
import 'core/diagnostics/diagnostic_category.dart';
import 'core/diagnostics/diagnostic_code.dart';
import 'core/diagnostics/diagnostic_error_boundary.dart';
import 'core/diagnostics/diagnostic_event.dart';
import 'core/diagnostics/diagnostic_level.dart';
import 'core/network/api_client.dart';

Future<void> main() async {
  final parentZone = Zone.current;
  var errorBoundary = DiagnosticErrorBoundary(
    diagnostics: const NoopAppDiagnostics(),
  );
  await runZonedGuarded(
    () async {
      final isFlutterTest = Platform.environment.containsKey('FLUTTER_TEST');
      if (kDebugMode && !isFlutterTest) {
        MarionetteBinding.ensureInitialized();
      } else {
        WidgetsFlutterBinding.ensureInitialized();
      }

      final diagnostics = DiagnosticPolicy.bootstrap();
      errorBoundary = isFlutterTest
          ? DiagnosticErrorBoundary(diagnostics: diagnostics)
          : installDiagnosticErrorBoundary(diagnostics);
      await _startApplication(diagnostics);
    },
    (error, stackTrace) =>
        errorBoundary.handleZonedAsyncError(parentZone, error, stackTrace),
  );
}

Future<void> _startApplication(AppDiagnostics diagnostics) async {
  try {
    final config = ConfigValidator.loadCompileTime();
    ClerkAuthAdapter? clerkAuth;
    if (config.clerkPublishableKey != null) {
      try {
        clerkAuth = await ClerkAuthAdapter.create(
          publishableKey: config.clerkPublishableKey!,
          diagnostics: diagnostics,
        );
      } catch (_) {
        clerkAuth = ClerkAuthAdapter.unavailable(diagnostics: diagnostics);
      }
    }
    final apiClient = ApiClient(
      config: config,
      authTokenProvider: clerkAuth,
      diagnostics: diagnostics,
    );
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.info,
        category: DiagnosticCategory.startup,
        code: DiagnosticCode.appStarted,
        context: DiagnosticContext(
          environment: config.environment,
          operation: DiagnosticOperation.startup,
        ),
      ),
    );
    runApp(
      _withDeviceConfig(
        SLFurnituresApp(
          config: config,
          apiClient: apiClient,
          ownsApiClient: true,
          authAdapter: clerkAuth,
          ownsAuthAdapter: true,
          diagnostics: diagnostics,
        ),
      ),
    );
  } on ConfigValidationException catch (error) {
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.critical,
        category: DiagnosticCategory.configuration,
        code: DiagnosticCode.configValidationFailed,
        context: const DiagnosticContext(
          operation: DiagnosticOperation.configuration,
        ),
      ),
    );
    runApp(_withDeviceConfig(ConfigFailureApp(message: error.message)));
  } catch (_) {
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.critical,
        category: DiagnosticCategory.startup,
        code: DiagnosticCode.appStartupFailed,
        context: const DiagnosticContext(
          operation: DiagnosticOperation.startup,
        ),
      ),
    );
    runApp(
      _withDeviceConfig(
        const ConfigFailureApp(
          message: 'The application could not start. Please try again later.',
        ),
      ),
    );
  }
}

/// Wraps the root widget so accessibility overrides such as text scale can be
/// driven during development verification.
///
/// The wrapper is compiled out of release builds, so it cannot ship.
Widget _withDeviceConfig(Widget child) =>
    kReleaseMode ? child : MarionetteDeviceConfig(child: child);
