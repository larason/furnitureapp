import 'package:flutter/foundation.dart';

import 'app_environment.dart';

/// Immutable, validated configuration for one application run.
///
/// Instances are produced only after [ConfigValidator.validate] succeeds, are
/// never mutated, and are passed to the widget tree by constructor injection.
/// The configuration layer owns no business rule beyond these values.
@immutable
class AppConfig {
  const AppConfig({
    required this.environment,
    required this.apiBaseUrl,
    this.clerkPublishableKey,
    this.enableDiagnostics = false,
  });

  /// Environment selected at build time through `APP_ENV`.
  final AppEnvironment environment;

  /// Validated API origin for the Laravel backend.
  ///
  /// This is an origin only: no `/api/v1` prefix, no path, query, fragment, or
  /// credentials, and no trailing slash. Phase 16.4 applies the frozen version
  /// and endpoint paths. Kept as a string so a configuration instance can be
  /// `const`, which is what makes immutability provable in tests.
  final String apiBaseUrl;

  /// Public Clerk publishable key, when the environment requires it.
  ///
  /// Publishable keys are client-safe by design. Secret keys are rejected by
  /// validation and must never reach this field.
  final String? clerkPublishableKey;

  /// Development diagnostics flag. Never enabled implicitly: it defaults to
  /// false and must be set deliberately in configuration.
  final bool enableDiagnostics;
}
