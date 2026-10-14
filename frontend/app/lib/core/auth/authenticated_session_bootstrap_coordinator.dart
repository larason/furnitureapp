import 'dart:async';

import 'package:flutter/widgets.dart';

import '../../features/account/data/profile_repository.dart';
import '../network/api_error.dart';
import '../network/api_transport_exception.dart';
import 'auth_session.dart';

/// Resolves Laravel's local user projection after an eligible Clerk session.
final class AuthenticatedSessionBootstrapCoordinator
    with WidgetsBindingObserver {
  AuthenticatedSessionBootstrapCoordinator({
    required this.session,
    required this.profiles,
    this.onSessionReady,
    List<Duration> retryDelays = const <Duration>[
      Duration(milliseconds: 250),
      Duration(seconds: 1),
    ],
  }) : _retryDelays = List<Duration>.unmodifiable(retryDelays);

  final AuthSession session;
  final ProfileRepository profiles;
  final void Function(String sessionId)? onSessionReady;
  final List<Duration> _retryDelays;

  String? _activeSessionId;
  String? _succeededSessionId;
  String? _inFlightSessionId;
  Timer? _retryTimer;
  int _generation = 0;
  int _retryAttempts = 0;
  bool? _lastFailureTransient;
  bool _started = false;
  bool _disposed = false;

  void start() {
    if (_started || _disposed) return;
    _started = true;
    session.addListener(_reconcile);
    WidgetsBinding.instance.addObserver(this);
    _reconcile();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _recover();
    }
  }

  void dispose() {
    if (_disposed) return;
    _disposed = true;
    _generation++;
    _retryTimer?.cancel();
    session.removeListener(_reconcile);
    WidgetsBinding.instance.removeObserver(this);
  }

  void _reconcile() {
    final sessionId = _eligibleSessionId;
    if (sessionId != _activeSessionId) {
      _generation++;
      _retryTimer?.cancel();
      _activeSessionId = sessionId;
      _succeededSessionId = null;
      _inFlightSessionId = null;
      _retryAttempts = 0;
      _lastFailureTransient = null;
    }
    if (sessionId == null ||
        _succeededSessionId == sessionId ||
        _lastFailureTransient == false) {
      return;
    }
    _retryTimer?.cancel();
    _retryTimer = null;
    _startBootstrap(sessionId, isRetry: _lastFailureTransient == true);
  }

  String? get _eligibleSessionId =>
      session.status == ClerkAuthStatus.signedIn && session.isSignedIn
      ? session.sessionId
      : null;

  void _startBootstrap(String sessionId, {bool isRetry = false}) {
    if (_inFlightSessionId == sessionId ||
        _disposed ||
        _succeededSessionId == sessionId ||
        (isRetry && _retryAttempts >= _retryDelays.length)) {
      return;
    }
    final generation = _generation;
    _inFlightSessionId = sessionId;
    if (isRetry) _retryAttempts++;
    unawaited(_bootstrap(sessionId, generation));
  }

  Future<void> _bootstrap(String sessionId, int generation) async {
    try {
      try {
        await profiles.getProfile();
      } catch (error) {
        _handleProfileFailure(sessionId, generation, error);
        return;
      }

      if (!_isCurrent(sessionId, generation)) return;
      try {
        onSessionReady?.call(sessionId);
        if (_isCurrent(sessionId, generation)) {
          _succeededSessionId = sessionId;
          _lastFailureTransient = null;
        }
      } catch (_) {
        _handleCallbackFailure(sessionId, generation);
      }
    } finally {
      if (_isCurrent(sessionId, generation) &&
          _inFlightSessionId == sessionId) {
        _inFlightSessionId = null;
      }
    }
  }

  void _handleProfileFailure(String sessionId, int generation, Object error) {
    if (!_isCurrent(sessionId, generation)) return;
    _lastFailureTransient = _isTransient(error);
    if (_lastFailureTransient!) {
      _scheduleRetry(sessionId, generation);
    }
  }

  void _handleCallbackFailure(String sessionId, int generation) {
    if (!_isCurrent(sessionId, generation)) return;
    _lastFailureTransient = true;
    _scheduleRetry(sessionId, generation);
  }

  bool _isCurrent(String sessionId, int generation) =>
      !_disposed && generation == _generation && sessionId == _activeSessionId;

  void _scheduleRetry(String sessionId, int generation) {
    if (_retryAttempts >= _retryDelays.length) return;
    final delay = _retryDelays[_retryAttempts];
    _retryTimer?.cancel();
    _retryTimer = Timer(delay, () {
      if (_isCurrent(sessionId, generation)) {
        _startBootstrap(sessionId, isRetry: true);
      }
    });
  }

  void _recover() {
    final sessionId = _eligibleSessionId;
    if (sessionId == null ||
        _succeededSessionId == sessionId ||
        _lastFailureTransient != true) {
      return;
    }
    _retryTimer?.cancel();
    _retryTimer = null;
    if (_retryAttempts >= _retryDelays.length) {
      _retryAttempts = 0;
    }
    _startBootstrap(sessionId, isRetry: true);
  }

  bool _isTransient(Object error) {
    if (error is ApiTransportException) {
      return error.kind != ApiTransportFailureKind.cancellation;
    }

    return error is ApiError &&
        (error.statusCode == 429 || error.statusCode >= 500);
  }
}
