import 'package:flutter/foundation.dart';

import 'device_registration_repository.dart';
import 'installation_identity.dart';
import 'serial_task_queue.dart';

/// Owns the client half of the device lifecycle: when a device is registered,
/// rotated, or retired for the current customer.
///
/// The rules this enforces exist because a token outlives a session. A device
/// must not stay registered to the previous customer after a sign-out, and a
/// token must never be moved between accounts on the strength of a
/// client-supplied value alone.
final class PushRegistrationCoordinator {
  PushRegistrationCoordinator({
    required this.repository,
    required this.tokenProvider,
    required this.tokenDeleter,
    required this.installationIdentity,
  });

  final DeviceRegistrationRepository repository;
  final Future<String?> Function() tokenProvider;
  final Future<void> Function() tokenDeleter;

  /// Supplies the stable per-installation identity. Required rather than
  /// optional so no call site can silently fall back to a value that changes
  /// on every token rotation.
  final InstallationIdentityStore installationIdentity;

  /// Server-assigned identifier of the device registered for the current
  /// customer, needed to retire exactly that device on sign-out.
  String? _registeredDeviceId;

  String? get registeredDeviceId => _registeredDeviceId;

  bool _inFlight = false;

  bool _registrationPending = false;

  bool _registrationTaskQueued = false;

  final SerialTaskQueue _tasks = SerialTaskQueue();

  int _sessionGeneration = 0;

  /// Registers (or rotates) this installation for the signed-in customer.
  ///
  /// Re-entrant calls are collapsed so a token refresh during startup cannot
  /// issue duplicate registrations. A call arriving while a registration is in
  /// flight records a pending request instead of being dropped: the token it
  /// would have sent is read again once that registration settles.
  Future<bool> registerForCustomer() {
    if (_registrationTaskQueued || _inFlight) {
      _registrationPending = true;

      return Future<bool>.value(false);
    }

    _registrationTaskQueued = true;

    return _tasks.add(() async {
      _registrationTaskQueued = false;

      return _registerForCustomer();
    });
  }

  Future<bool> _registerForCustomer() async {
    if (_inFlight) {
      _registrationPending = true;

      return false;
    }

    final registered = await _registerOnce();

    return await _drainPendingRegistration() ?? registered;
  }

  /// The token the server last accepted, used to decide whether a collapsed
  /// call is worth retrying.
  String? _lastSentToken;

  Future<bool?> _drainPendingRegistration() async {
    if (!_registrationPending) {
      return null;
    }
    _registrationPending = false;

    // A collapsed call that carries the token the server already holds adds
    // nothing, so only a genuine rotation is worth another request. When the
    // settled attempt failed, [_lastSentToken] still holds an older value, so
    // the same token is retried instead of being skipped.
    final current = await tokenProvider();
    if (current == null || current.isEmpty || current == _lastSentToken) {
      return null;
    }

    return _registerOnce();
  }

  Future<bool> _registerOnce() async {
    final generation = _sessionGeneration;
    _inFlight = true;
    try {
      final token = await tokenProvider();
      if (token == null || token.isEmpty) {
        return false;
      }

      final device = await repository.register(
        token: token,
        appInstanceId: await installationIdentity.getOrCreate(),
      );
      if (generation != _sessionGeneration) {
        return false;
      }
      _registeredDeviceId = device.id;
      _lastSentToken = token;

      return true;
    } catch (error, stackTrace) {
      _recordFailure('registration', error, stackTrace);
      return false;
    } finally {
      _inFlight = false;
    }
  }

  /// Retires this device for the previous customer before the session ends.
  ///
  /// Cleanup is best effort in the sense that a failure is recorded and the
  /// local token is still discarded. It is *not* best effort about ownership:
  /// the server refuses to hand a device to a new account, so a failed
  /// deactivation surfaces as a conflict on the next registration rather than
  /// as a silent takeover.
  Future<void> retireOnSignOut() => _tasks.add(_retireOnSignOut);

  Future<void> _retireOnSignOut() async {
    _sessionGeneration++;
    final deviceId = _registeredDeviceId;
    _registeredDeviceId = null;

    // The coordinator outlives any single session, so the previous account's
    // bookkeeping must be cleared with it. Leaving `_lastSentToken` set would
    // make the next account look like a redundant re-send when Firebase hands
    // back the same token value, and a stale pending flag would either fire a
    // registration for the new account or suppress one.
    _lastSentToken = null;
    _registrationPending = false;

    if (deviceId != null) {
      try {
        await repository.deactivate(deviceId);
      } catch (error, stackTrace) {
        _recordFailure('deactivation', error, stackTrace);
      }
    }

    try {
      await tokenDeleter();
    } catch (error, stackTrace) {
      _recordFailure('token deletion', error, stackTrace);
    }
  }

  /// Reconciles this device with a change of session.
  ///
  /// On sign-in the current token is registered. On an account switch the
  /// previous association is retired and the local token discarded *before* the
  /// new account is registered, so a device is never silently reassigned. If
  /// retirement failed, the server-side conflict on the next registration is
  /// the intended fail-closed outcome rather than a takeover.
  Future<void> reconcileSession({
    required bool isSignedIn,
    required bool wasSignedIn,
    Future<void> Function()? tokenRefresher,
  }) => _tasks.add(
    () => _reconcileSession(
      isSignedIn: isSignedIn,
      wasSignedIn: wasSignedIn,
      tokenRefresher: tokenRefresher,
    ),
  );

  Future<void> _reconcileSession({
    required bool isSignedIn,
    required bool wasSignedIn,
    Future<void> Function()? tokenRefresher,
  }) async {
    if (!isSignedIn) {
      if (wasSignedIn) {
        await _retireOnSignOut();
      }
      _registeredDeviceId = null;

      return;
    }

    if (wasSignedIn) {
      await _retireOnSignOut();
    }

    if (tokenRefresher != null) {
      await tokenRefresher();
    }
    await _registerForCustomer();
  }

  void _recordFailure(String operation, Object error, StackTrace stackTrace) {
    assert(() {
      debugPrint('Push device $operation failed: $error');
      return true;
    }());
  }
}
