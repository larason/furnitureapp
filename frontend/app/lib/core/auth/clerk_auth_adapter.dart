import 'dart:async';

import 'package:clerk_auth/clerk_auth.dart';
import 'package:flutter/foundation.dart';

import '../network/auth_token_provider.dart';
import 'secure_clerk_persistor.dart';

enum ClerkAuthStatus {
  initializing,
  signedOut,
  signedIn,
  actionRequired,
  temporarilyUnavailable,
}

abstract interface class ClerkAuthGateway {
  Stream<void> get changes;
  bool get actionRequired;
  bool get isSignedIn;
  Future<void> initialize();
  Future<String?> getSessionToken();
  Future<void> signOut();
  void terminate();
}

class ClerkAuthAdapter extends ChangeNotifier implements AuthTokenProvider {
  ClerkAuthAdapter({required this._gateway});

  ClerkAuthAdapter.unavailable() : _gateway = _UnavailableGateway() {
    _status = ClerkAuthStatus.temporarilyUnavailable;
  }

  final ClerkAuthGateway _gateway;
  late final StreamSubscription<void> _changesSubscription = _gateway.changes
      .listen(_handleGatewayChange);
  ClerkAuthStatus _status = ClerkAuthStatus.initializing;
  int _sessionGeneration = 0;
  bool _localSessionBlocked = false;
  bool _signOutInProgress = false;
  bool _disposed = false;

  ClerkAuthStatus get status => _status;
  bool get isSignedIn =>
      !_localSessionBlocked && !_signOutInProgress && _gateway.isSignedIn;

  static Future<ClerkAuthAdapter> create({
    required String publishableKey,
    SecureKeyValueStore? store,
  }) async {
    final persistor = SecureClerkPersistor(
      store: store ?? FlutterSecureKeyValueStore(),
    );
    final auth = Auth(
      config: AuthConfig(
        publishableKey: publishableKey,
        persistor: persistor,
        sessionTokenPolling: true,
      ),
    );
    final adapter = ClerkAuthAdapter(gateway: _ClerkAuthGateway(auth));
    await adapter.initialize();
    return adapter;
  }

  Future<void> initialize() async {
    try {
      await _gateway.initialize();
      _localSessionBlocked = false;
      _setStatus(_currentStatus);
    } catch (_) {
      _setStatus(ClerkAuthStatus.temporarilyUnavailable);
      rethrow;
    }
  }

  @override
  Future<String?> getToken() async {
    if (_localSessionBlocked || _signOutInProgress || !_gateway.isSignedIn) {
      return null;
    }

    final generation = _sessionGeneration;
    final token = await _gateway.getSessionToken();
    if (generation != _sessionGeneration ||
        _localSessionBlocked ||
        _signOutInProgress ||
        !_gateway.isSignedIn) {
      return null;
    }
    return token;
  }

  Future<void> signOut() async {
    _sessionGeneration++;
    _localSessionBlocked = true;
    _signOutInProgress = true;
    _setStatus(ClerkAuthStatus.signedOut);
    try {
      await _gateway.signOut();
      _setStatus(ClerkAuthStatus.signedOut);
    } catch (_) {
      _setStatus(ClerkAuthStatus.temporarilyUnavailable);
      rethrow;
    } finally {
      _signOutInProgress = false;
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _changesSubscription.cancel();
    _gateway.terminate();
    super.dispose();
  }

  void _handleGatewayChange(void _) {
    _setStatus(_currentStatus);
  }

  ClerkAuthStatus get _currentStatus {
    if (_localSessionBlocked || _signOutInProgress) {
      return ClerkAuthStatus.signedOut;
    }
    if (_gateway.actionRequired) return ClerkAuthStatus.actionRequired;
    return _gateway.isSignedIn
        ? ClerkAuthStatus.signedIn
        : ClerkAuthStatus.signedOut;
  }

  void _setStatus(ClerkAuthStatus status) {
    if (_status == status || _disposed) return;
    _status = status;
    notifyListeners();
  }
}

class _ClerkAuthGateway implements ClerkAuthGateway {
  _ClerkAuthGateway(this._auth);

  final Auth _auth;

  @override
  Stream<void> get changes => _auth.sessionTokenStream.map<void>((_) {});

  @override
  bool get actionRequired =>
      _auth.session?.status == Status.pending ||
      _auth.isSigningIn ||
      _auth.isSigningUp;

  @override
  bool get isSignedIn =>
      _auth.isSignedIn && _auth.session?.status.isActive == true;

  @override
  Future<void> initialize() => _auth.initialize();

  @override
  Future<String?> getSessionToken() async {
    if (!_auth.isSignedIn) return null;
    return (await _auth.sessionToken()).jwt;
  }

  @override
  Future<void> signOut() => _auth.signOut();

  @override
  void terminate() => _auth.terminate();
}

class _UnavailableGateway implements ClerkAuthGateway {
  @override
  Stream<void> get changes => const Stream<void>.empty();

  @override
  bool get actionRequired => false;

  @override
  bool get isSignedIn => false;

  @override
  Future<void> initialize() async {}

  @override
  Future<String?> getSessionToken() async => null;

  @override
  Future<void> signOut() async {}

  @override
  void terminate() {}
}
