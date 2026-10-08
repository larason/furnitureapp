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
}
