import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../../../firebase_options.dart';
import '../auth/auth_session.dart';
import 'push_registration_coordinator.dart';
import 'serial_task_queue.dart';

@immutable
class PushMessageSignal {
  const PushMessageSignal({this.messageId, this.data = const {}});

  final String? messageId;
  final Map<String, String> data;
}

typedef PushTokenChanged = void Function(String? token);

/// Holds messages that arrived before any consumer could subscribe.
///
/// A notification that opens the application is read by `getInitialMessage()`
/// during initialization. Publishing it immediately would be lost, because
/// `signals` is a broadcast stream with no listener yet. The buffer is drained
/// exactly once, on the first subscription or on an explicit take.
final class PushInitialSignalBuffer {
  PushInitialSignalBuffer([List<PushMessageSignal>? initial])
    : _signals = List<PushMessageSignal>.of(initial ?? const []);

  final List<PushMessageSignal> _signals;

  bool _drained = false;

  bool get isEmpty => _signals.isEmpty;

  bool get isDrained => _drained;

  void add(PushMessageSignal signal) {
    _signals.add(signal);
  }

  /// Returns the buffered signals and marks the buffer drained.
  List<PushMessageSignal> drain() {
    if (_drained || _signals.isEmpty) {
      return const [];
    }
    _drained = true;

    return List<PushMessageSignal>.unmodifiable(_signals);
  }
}

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }
}

final class PushMessagingService extends ChangeNotifier {
  PushMessagingService._(this._messaging, {this.registrations});

  final FirebaseMessaging _messaging;

  /// Client half of the device lifecycle. Absent until the application wires it
  /// to an authenticated repository, so a token is never sent to a destination
  /// that has no verified session.
  final PushRegistrationCoordinator? registrations;
  final StreamController<PushMessageSignal> _signals =
      StreamController<PushMessageSignal>.broadcast();
  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  StreamSubscription<String>? _tokenSubscription;
  AuthSession? _authSession;
  String? _token;
  bool _wasSignedIn = false;
  bool _disposed = false;

  static bool _backgroundHandlerRegistered = false;

  /// Serializes auth-driven work; see [_enqueueAuthChange].
  final SerialTaskQueue _authChanges = SerialTaskQueue();

  /// Initializes Firebase Messaging and starts message listeners.
  ///
  /// A message that opened the application is buffered rather than published,
  /// because [signals] is a broadcast stream and no consumer can yet have
  /// subscribed during initialization. It is replayed by [signals] or readable
  /// through [takePendingInitialSignal].
  static Future<PushMessagingService?> initialize({
    PushRegistrationCoordinator? registrations,
  }) async {
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }

      final service = PushMessagingService._(
        FirebaseMessaging.instance,
        registrations: registrations,
      );
      service._registerListeners();
      final initialMessage = await service._messaging.getInitialMessage();
      if (initialMessage != null) {
        service._initialBuffer.add(service._toSignal(initialMessage));
      }

      return service;
    } catch (_) {
      return null;
    }
  }

  /// Messages delivered while the application was not running.
  ///
  /// A message that launched the application is emitted into [_signals] during
  /// [initialize], before any consumer can subscribe, so it would otherwise be
  /// lost. It is buffered here and replayed to the first consumer instead.
  /// Deferred until a consumer can safely decide what to do with it, because
  /// acting on it immediately could navigate a customer who is not yet
  /// authenticated to a destination that requires a session.
  final PushInitialSignalBuffer _initialBuffer = PushInitialSignalBuffer();

  bool _drainBound = false;

  Stream<PushMessageSignal> get signals {
    // Draining on the controller's onListen, not on this getter: a getter
    // access happens before the caller has subscribed, so flushing there would
    // replay into a stream that still has no listener and lose the message.
    if (!_drainBound) {
      _drainBound = true;
      _signals.onListen = _drainBufferedSignals;
    }

    return _signals.stream;
  }

  /// A message that opened the application, available before [signals] is
  /// consumed. Returns `null` once the buffer has been drained or when the
  /// application was not opened by a notification.
  PushMessageSignal? takePendingInitialSignal() {
    final pending = _initialBuffer.drain();

    return pending.isEmpty ? null : pending.first;
  }

  void _drainBufferedSignals() {
    for (final signal in _initialBuffer.drain()) {
      if (!_signals.isClosed) {
        _signals.add(signal);
      }
    }
  }

  String? get currentToken => _token;

  AuthorizationStatus? get authorizationStatus => _authorizationStatusCache;

  AuthorizationStatus? _authorizationStatusCache;

  void attachAuthSession(AuthSession session) {
    if (identical(_authSession, session)) {
      return;
    }

    _authSession?.removeListener(_handleAuthListener);
    _authSession = session;
    session.addListener(_handleAuthListener);
    unawaited(_enqueueAuthChange());
  }

  void _handleAuthListener() {
    unawaited(_enqueueAuthChange());
  }

  /// Session changes are serialized so a sign-out always runs after any
  /// in-flight sign-in registration has settled. Without this, a sign-out that
  /// overlaps a registration finds no device id to retire and the registration
  /// then completes, leaving the device registered to an account that has left.
  Future<void> _enqueueAuthChange() => _authChanges.add(_handleAuthChange);

  Future<AuthorizationStatus> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
    _authorizationStatusCache = settings.authorizationStatus;
    notifyListeners();

    return settings.authorizationStatus;
  }

  /// Resolves a registration token for a local FCM transport diagnostic.
  ///
  /// Returns `null` in release builds. The token is returned for a one-off
  /// provider check only: it is never stored in [_token], never persisted, and
  /// never transmitted. This is not a device-registration path.
  Future<String?> resolveDiagnosticToken() async {
    if (kReleaseMode) {
      return null;
    }

    try {
      return await _messaging.getToken();
    } catch (_) {
      return null;
    }
  }

  Future<AuthorizationStatus> refreshPermissionStatus() async {
    final settings = await _messaging.getNotificationSettings();
    _authorizationStatusCache = settings.authorizationStatus;
    notifyListeners();

    return settings.authorizationStatus;
  }

  Future<String?> refreshToken() async {
    if (_authSession?.isSignedIn != true) {
      _clearToken();

      return null;
    }

    try {
      final token = await _messaging.getToken();
      _setToken(token);

      return token;
    } catch (_) {
      return null;
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _authSession?.removeListener(_handleAuthListener);
    unawaited(_foregroundSubscription?.cancel());
    unawaited(_openedSubscription?.cancel());
    unawaited(_tokenSubscription?.cancel());
    unawaited(_signals.close());
    super.dispose();
  }

  void _registerListeners() {
    if (!_backgroundHandlerRegistered) {
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      _backgroundHandlerRegistered = true;
    }

    _foregroundSubscription = FirebaseMessaging.onMessage.listen(_emit);
    _openedSubscription = FirebaseMessaging.onMessageOpenedApp.listen(_emit);
    _tokenSubscription = _messaging.onTokenRefresh.listen(_handleTokenRefresh);
  }

  Future<void> _handleAuthChange() async {
    final isSignedIn = _authSession?.isSignedIn == true;
    final coordinator = registrations;
    final wasSignedIn = _wasSignedIn;
    _wasSignedIn = isSignedIn;

    if (coordinator == null) {
      if (!isSignedIn) {
        if (wasSignedIn) {
          await _deleteLocalToken();
        }
        _clearToken();

        return;
      }

      await refreshToken();

      return;
    }

    await coordinator.reconcileSession(
      isSignedIn: isSignedIn,
      wasSignedIn: wasSignedIn,
      tokenRefresher: isSignedIn ? refreshToken : null,
    );

    if (!isSignedIn) {
      _clearToken();
    }
  }

  void _handleTokenRefresh(String token) {
    if (_authSession?.isSignedIn != true) {
      return;
    }

    _setToken(token);
    // Rotation reuses the same registration endpoint, so the server record
    // converges on the new token instead of accumulating a second device.
    unawaited(registrations?.registerForCustomer() ?? Future<void>.value());
  }

  /// Discards the local token, so the next sign-in or account switch receives a
  /// fresh one instead of reusing a token another account may have owned.
  Future<void> discardLocalToken() async {
    await _deleteLocalToken();
    _clearToken();
  }

  Future<void> _deleteLocalToken() async {
    try {
      await _messaging.deleteToken();
    } catch (_) {
      // A later sign-in obtains a fresh token; cleanup is best effort.
    }
  }

  void _setToken(String? token) {
    if (_token == token || _disposed) {
      return;
    }

    _token = token;
    notifyListeners();
  }

  void _clearToken() {
    _setToken(null);
  }

  void _emit(RemoteMessage message) {
    if (_disposed || _signals.isClosed) {
      return;
    }

    _signals.add(_toSignal(message));
  }

  PushMessageSignal _toSignal(RemoteMessage message) => PushMessageSignal(
    messageId: message.messageId,
    data: Map<String, String>.from(message.data),
  );
}
