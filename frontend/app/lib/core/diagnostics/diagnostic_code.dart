enum DiagnosticCode {
  appStarted,
  appStartupFailed,
  configValidationFailed,
  apiRequestFailed,
  apiRequestTimeout,
  apiConnectionFailed,
  apiInvalidResponse,
  apiRateLimited,
  authInitializationFailed,
  authSessionRestoreFailed,
  authSessionRenewalFailed,
  authSignOutFailed,
  securePersistenceFailed,
  navigationRouteUnavailable,
  navigationInvalidParameter,
  navigationUnsafeDestination,
  unexpectedFlutterError,
  unhandledAsyncError,
}

extension DiagnosticCodeValue on DiagnosticCode {
  String get value => switch (this) {
    DiagnosticCode.appStarted => 'APP_STARTED',
    DiagnosticCode.appStartupFailed => 'APP_STARTUP_FAILED',
    DiagnosticCode.configValidationFailed => 'CONFIG_VALIDATION_FAILED',
    DiagnosticCode.apiRequestFailed => 'API_REQUEST_FAILED',
    DiagnosticCode.apiRequestTimeout => 'API_REQUEST_TIMEOUT',
    DiagnosticCode.apiConnectionFailed => 'API_CONNECTION_FAILED',
    DiagnosticCode.apiInvalidResponse => 'API_INVALID_RESPONSE',
    DiagnosticCode.apiRateLimited => 'API_RATE_LIMITED',
    DiagnosticCode.authInitializationFailed => 'AUTH_INITIALIZATION_FAILED',
    DiagnosticCode.authSessionRestoreFailed => 'AUTH_SESSION_RESTORE_FAILED',
    DiagnosticCode.authSessionRenewalFailed => 'AUTH_SESSION_RENEWAL_FAILED',
    DiagnosticCode.authSignOutFailed => 'AUTH_SESSION_SIGN_OUT_FAILED',
    DiagnosticCode.securePersistenceFailed => 'SECURE_PERSISTENCE_FAILED',
    DiagnosticCode.navigationRouteUnavailable => 'NAVIGATION_ROUTE_UNAVAILABLE',
    DiagnosticCode.navigationInvalidParameter => 'NAVIGATION_INVALID_PARAMETER',
    DiagnosticCode.navigationUnsafeDestination =>
      'NAVIGATION_UNSAFE_DESTINATION',
    DiagnosticCode.unexpectedFlutterError => 'UNEXPECTED_FLUTTER_ERROR',
    DiagnosticCode.unhandledAsyncError => 'UNHANDLED_ASYNC_ERROR',
  };
}
