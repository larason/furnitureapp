import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/clerk_auth_adapter.dart';
import 'app_routes.dart';

class RouteGuard {
  const RouteGuard(this._getAuthStatus);

  final ClerkAuthStatus Function() _getAuthStatus;

  String? redirect(BuildContext context, GoRouterState state) {
    final path = state.uri.path;
    final intended = AppRoutes.safeIntendedDestination(
      state.uri.queryParameters['continue'],
    );
    final authStatus = _getAuthStatus();

    if (AppRoutes.isProtectedPath(path)) {
      return _redirectProtected(state.uri.toString(), authStatus);
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

  String? _redirectProtected(String destination, ClerkAuthStatus authStatus) {
    return switch (authStatus) {
      ClerkAuthStatus.signedIn => null,
      ClerkAuthStatus.signedOut => AppRoutes.signInWithDestination(destination),
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
