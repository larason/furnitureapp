import 'package:flutter/foundation.dart';

import '../network/auth_token_provider.dart';

enum ClerkAuthStatus {
  initializing,
  signedOut,
  signedIn,
  actionRequired,
  temporarilyUnavailable,
}

/// SDK-independent authentication boundary for feature modules.
abstract interface class AuthSession implements Listenable, AuthTokenProvider {
  ClerkAuthStatus get status;

  bool get isSignedIn;

  Future<void> signOut();

  /// Registers cleanup that still needs the outgoing session's bearer token.
  ///
  /// [signOut] awaits these listeners before it blocks token access and revokes
  /// the Clerk session, so work such as retiring a registered push device can
  /// authenticate against the previous account. Clerk sign-out cannot be rolled
  /// back, so a listener that fails must not abort it.
  ///
  /// Implementations must declare this: every [AuthSession] is required to decide
  /// whether it can drain such cleanup before sign-out.
  void addBeforeSignOutListener(Future<void> Function() listener);

  /// Removes previously registered pre-sign-out cleanup.
  void removeBeforeSignOutListener(Future<void> Function() listener);
}
