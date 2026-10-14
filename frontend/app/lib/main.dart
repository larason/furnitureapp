import 'dart:io';
import 'dart:async';

import 'package:flutter/foundation.dart' hide DiagnosticLevel;
import 'package:flutter/material.dart' hide DiagnosticLevel;
import 'package:marionette_flutter/marionette_flutter.dart';

import 'app.dart';
import 'config/config_validation.dart';
import 'core/auth/clerk_auth_adapter.dart';
import 'core/auth/secure_clerk_persistor.dart';
import 'core/diagnostics/app_diagnostics.dart';
import 'core/diagnostics/diagnostic_category.dart';
import 'core/diagnostics/diagnostic_code.dart';
import 'core/diagnostics/diagnostic_error_boundary.dart';
import 'core/diagnostics/diagnostic_event.dart';
import 'core/diagnostics/diagnostic_level.dart';
import 'core/network/api_client.dart';
import 'core/notifications/device_registration_repository.dart';
import 'core/notifications/installation_identity.dart';
import 'core/notifications/push_messaging_service.dart';
import 'core/notifications/push_registration_coordinator.dart';

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
    // The coordinator and the messaging service reference each other: the
    // coordinator supplies the token, the service triggers registration. The
    // late-bound holder breaks the cycle without a second token service.
    PushMessagingService? messaging;
    final coordinator = config.enablePushRegistration
        ? PushRegistrationCoordinator(
            repository: ApiDeviceRegistrationRepository(apiClient),
            tokenProvider: () async => messaging?.refreshToken(),
            tokenDeleter: () async => messaging?.discardLocalToken(),
            installationIdentity: SecureStorageInstallationIdentityStore(
              FlutterSecureKeyValueStore(),
            ),
          )
        : null;
    messaging = await PushMessagingService.initialize(
      registrations: coordinator,
    );
    final pushMessaging = messaging;
    await _runPushDiagnostic(pushMessaging);
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
          pushMessaging: pushMessaging,
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

/// Opt-in local FCM transport check.
///
/// Requires a debug build started with
/// `--dart-define=FCM_DIAGNOSTIC_TOKEN_DUMP=true`. It prints the token that a
/// provider diagnostic needs and runs the existing explicit notification
/// permission flow. It is compiled out of release builds, persists nothing, and
/// sends nothing to Laravel.
Future<void> _runPushDiagnostic(PushMessagingService? pushMessaging) async {
  const requested = String.fromEnvironment('FCM_DIAGNOSTIC_TOKEN_DUMP');
  if (!kDebugMode || requested != 'true' || pushMessaging == null) {
    return;
  }

  pushMessaging.signals.listen((signal) {
    debugPrint(
      'FCM_DIAGNOSTIC_SIGNAL messageId=${signal.messageId} '
      'data=${signal.data}',
    );
  });

  final pending = pushMessaging.takePendingInitialSignal();
  if (pending != null) {
    debugPrint(
      'FCM_DIAGNOSTIC_INITIAL_SIGNAL messageId=${pending.messageId} '
      'data=${pending.data}',
    );
  }

  final token = await pushMessaging.resolveDiagnosticToken();
  debugPrint('FCM_DIAGNOSTIC_TOKEN=$token');

  final status = await pushMessaging.requestPermission();
  debugPrint('FCM_DIAGNOSTIC_PERMISSION=${status.name}');
}
