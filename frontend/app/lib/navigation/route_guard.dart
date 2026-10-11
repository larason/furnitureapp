import 'package:flutter/widgets.dart' hide DiagnosticLevel;
import 'package:go_router/go_router.dart';

import '../core/auth/clerk_auth_adapter.dart';
import '../core/diagnostics/app_diagnostics.dart';
import '../core/diagnostics/diagnostic_category.dart';
import '../core/diagnostics/diagnostic_code.dart';
import '../core/diagnostics/diagnostic_event.dart';
import '../core/diagnostics/diagnostic_level.dart';
import 'app_routes.dart';

/// Decides where a request belongs for the current authentication state.
///
/// The guard is stateful because sign-out is a transition, not a state: a
/// customer who signs out from a protected screen should land on the public
/// catalog, not be pushed into a sign-in form they just completed.
class RouteGuard {
  RouteGuard(this._getAuthStatus, {this.diagnostics = const NoopAppDiagnostics()});

  final ClerkAuthStatus Function() _getAuthStatus;
  final AppDiagnostics diagnostics;

  ClerkAuthStatus? _lastObserved;

  String? redirect(BuildContext context, GoRouterState state) {
    final path = state.uri.path;
    final intended = AppRoutes.safeIntendedDestination(
      state.uri.queryParameters['continue'],
    );
    if (state.uri.queryParameters['continue'] != null && intended == null) {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.warning,
          category: DiagnosticCategory.navigation,
          code: DiagnosticCode.navigationUnsafeDestination,
          context: const DiagnosticContext(
            operation: DiagnosticOperation.navigation,
            routeFailure: DiagnosticRouteFailure.unsafeDestination,
          ),
        ),
      );
    }
    final authStatus = _getAuthStatus();
    final endedSession = _lastObserved == ClerkAuthStatus.signedIn &&
        authStatus == ClerkAuthStatus.signedOut;
    _lastObserved = authStatus;

    if (AppRoutes.isProtectedPath(path)) {
      return _redirectProtected(state.uri.toString(), authStatus, endedSession);
    }

    if (path == AppRoutes.authStatus) {
      return _redirectAuthStatus(intended, authStatus);
    }

    if ((path == AppRoutes.signIn || path == AppRoutes.signUp) &&
        authStatus == ClerkAuthStatus.signedIn) {
      return intended ?? AppRoutes.home;
    }

    return null;
  }

  String? _redirectProtected(
    String destination,
    ClerkAuthStatus authStatus,
    bool endedSession,
  ) {
    return switch (authStatus) {
      ClerkAuthStatus.signedIn => null,
      ClerkAuthStatus.signedOut => endedSession
          ? AppRoutes.home
          : AppRoutes.signInWithDestination(destination),
      ClerkAuthStatus.initializing ||
      ClerkAuthStatus.actionRequired ||
      ClerkAuthStatus.temporarilyUnavailable =>
        AppRoutes.authStatusWithDestination(destination),
    };
  }

  String? _redirectAuthStatus(String? intended, ClerkAuthStatus authStatus) {
    return switch (authStatus) {
      ClerkAuthStatus.signedIn => intended ?? AppRoutes.home,
      ClerkAuthStatus.signedOut => AppRoutes.signInWithDestination(
        intended ?? AppRoutes.home,
      ),
      ClerkAuthStatus.initializing ||
      ClerkAuthStatus.actionRequired ||
      ClerkAuthStatus.temporarilyUnavailable => null,
    };
  }
}
