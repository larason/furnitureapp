import 'dart:async';

import 'package:clerk_auth/clerk_auth.dart';
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/foundation.dart' hide DiagnosticLevel;

import 'auth_session.dart';
import 'secure_clerk_persistor.dart';
import '../diagnostics/app_diagnostics.dart';
import '../diagnostics/diagnostic_category.dart';
import '../diagnostics/diagnostic_code.dart';
import '../diagnostics/diagnostic_event.dart';
import '../diagnostics/diagnostic_level.dart';

export 'auth_session.dart';

abstract interface class ClerkAuthGateway {
  Stream<void> get changes;
  bool get actionRequired;
  bool get isSignedIn;
  Future<void> initialize();
  Future<String?> getSessionToken();
  Future<void> signOut();
  void terminate();
}

class ClerkAuthAdapter extends ChangeNotifier implements AuthSession {
  ClerkAuthAdapter({
    required this._gateway,
    this.diagnostics = const NoopAppDiagnostics(),
  }) {
    _changesSubscription = _gateway.changes.listen(_handleGatewayChange);
  }

  ClerkAuthAdapter.unavailable({this.diagnostics = const NoopAppDiagnostics()})
    : _gateway = _UnavailableGateway() {
    _changesSubscription = _gateway.changes.listen(_handleGatewayChange);
    _status = ClerkAuthStatus.temporarilyUnavailable;
  }

  final ClerkAuthGateway _gateway;
  final AppDiagnostics diagnostics;
  late final StreamSubscription<void> _changesSubscription;
  ClerkAuthStatus _status = ClerkAuthStatus.initializing;
  int _sessionGeneration = 0;
  bool _localSessionBlocked = false;
  bool _signOutInProgress = false;
  bool _disposed = false;

  ClerkAuthState? get clerkAuthState => switch (_gateway) {
    _ClerkAuthGateway(:final auth) => auth,
    _ => null,
  };

  @override
  ClerkAuthStatus get status => _status;

  @override
  bool get isSignedIn =>
      !_localSessionBlocked && !_signOutInProgress && _gateway.isSignedIn;

  static Future<ClerkAuthAdapter> create({
    required String publishableKey,
    SecureKeyValueStore? store,
    AppDiagnostics diagnostics = const NoopAppDiagnostics(),
  }) async {
    final persistor = SecureClerkPersistor(
      store: store ?? FlutterSecureKeyValueStore(),
      diagnostics: diagnostics,
    );
    final auth = await ClerkAuthState.create(
      config: ClerkAuthConfig(
        publishableKey: publishableKey,
        persistor: persistor,
        sessionTokenPolling: true,
      ),
    );
    final adapter = ClerkAuthAdapter(
      gateway: _ClerkAuthGateway(auth),
      diagnostics: diagnostics,
    );
    try {
      await adapter.initialize();
      return adapter;
    } catch (error, stackTrace) {
      try {
        adapter.dispose();
      } catch (_) {
        // Preserve the initialization failure as the public error.
      }
      Error.throwWithStackTrace(error, stackTrace);
    }
  }

  Future<void> initialize() async {
    try {
      await _gateway.initialize();
      _setStatus(_currentStatus);
    } catch (_) {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.error,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.authSessionRestoreFailed,
          context: const DiagnosticContext(
            operation: DiagnosticOperation.authSessionRestore,
          ),
        ),
      );
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
    late final String? token;
    try {
      token = await _gateway.getSessionToken();
    } catch (_) {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.warning,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.authSessionRenewalFailed,
          context: const DiagnosticContext(
            operation: DiagnosticOperation.authSessionRenewal,
          ),
        ),
      );
      rethrow;
    }
    if (generation != _sessionGeneration ||
        _localSessionBlocked ||
        _signOutInProgress ||
        !_gateway.isSignedIn) {
      return null;
    }
    return token;
  }

  @override
  Future<void> signOut() async {
    _sessionGeneration++;
    _localSessionBlocked = true;
    _signOutInProgress = true;
    _setStatus(ClerkAuthStatus.signedOut);
    try {
      await _gateway.signOut();
      _localSessionBlocked = false;
      _setStatus(ClerkAuthStatus.signedOut);
    } catch (_) {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.error,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.authSignOutFailed,
          context: const DiagnosticContext(
            operation: DiagnosticOperation.authSignOut,
          ),
        ),
      );
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

  final ClerkAuthState _auth;

  ClerkAuthState get auth => _auth;

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
  Future<void> initialize() async {}

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
