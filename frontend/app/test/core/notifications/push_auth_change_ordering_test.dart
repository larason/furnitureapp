import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';
import 'package:sl_furnitures/core/notifications/installation_identity.dart';
import 'package:sl_furnitures/core/notifications/push_registration_coordinator.dart';
import 'package:sl_furnitures/core/notifications/serial_task_queue.dart';

/// A repository whose registration blocks until released, so a session change
/// can be made to overlap an in-flight sign-in.
class _GatedRepository implements DeviceRegistrationRepository {
  final List<String> trace = [];
  Completer<void>? gate;

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    trace.add('register:start');
    await gate?.future;
    trace.add('register:end');

    return RegisteredDevice(
      id: 'dev_1',
      platform: 'ANDROID',
      appInstanceId: appInstanceId,
      status: 'ACTIVE',
    );
  }

  @override
  Future<void> deactivate(String deviceId, {RequestCancellation? cancellation}) async {
    trace.add('deactivate');
  }
}

class _Identity implements InstallationIdentityStore {
  @override
  Future<String> getOrCreate() async => 'installation-abc';
}

/// Mirrors the auth-change handling in `PushMessagingService`, so the ordering
/// guarantee can be exercised without a live Firebase session.
class _AuthChangeHandler {
  _AuthChangeHandler({
    required this.coordinator,
    required this.isSignedIn,
    required this.trace,
  });

  final PushRegistrationCoordinator coordinator;
  final bool Function() isSignedIn;
  final List<String> trace;

  final SerialTaskQueue queue = SerialTaskQueue();
  bool _wasSignedIn = false;

  Future<void> handle() => queue.add(_run);

  Future<void> _run() async {
    final signedIn = isSignedIn();
    final wasSignedIn = _wasSignedIn;
    _wasSignedIn = signedIn;

    await coordinator.reconcileSession(
      isSignedIn: signedIn,
      wasSignedIn: wasSignedIn,
      tokenRefresher: signedIn ? () async => trace.add('refresh-token') : null,
    );
  }
}

/// A sign-out must not overlap an in-flight sign-in registration.
///
/// Without serialization the sign-out finds no device to retire — the
/// registration has not yet returned an id — and the registration then
/// completes, leaving the device registered to an account that has left.
void main() {
  late _GatedRepository repository;
  late PushRegistrationCoordinator coordinator;
  late _AuthChangeHandler handler;
  late bool signedIn;

  setUp(() {
    repository = _GatedRepository();
    coordinator = PushRegistrationCoordinator(
      repository: repository,
      tokenProvider: () async => 'token-1',
      tokenDeleter: () async => repository.trace.add('delete-token'),
      installationIdentity: _Identity(),
    );
    signedIn = false;
    handler = _AuthChangeHandler(
      coordinator: coordinator,
      isSignedIn: () => signedIn,
      trace: repository.trace,
    );
  });

  test('a sign-out overlapping a sign-in still retires the device', () async {
    repository.gate = Completer<void>();

    // Sign in: registration starts and blocks on the network.
    signedIn = true;
    final signIn = handler.handle();
    await Future<void>.delayed(Duration.zero);
    expect(repository.trace, ['refresh-token', 'register:start']);

    // Sign out while that registration is still in flight.
    signedIn = false;
    final signOut = handler.handle();
    await Future<void>.delayed(const Duration(milliseconds: 10));

    // The sign-out must not have run yet: it is queued behind the registration.
    expect(repository.trace, isNot(contains('deactivate')));

    repository.gate!.complete();
    await signIn;
    await signOut;

    expect(repository.trace, [
      'refresh-token',
      'register:start',
      'register:end',
      'deactivate',
      'delete-token',
    ]);
  });

  test('a sign-out following a sign-in runs after the registration settles', () async {
    signedIn = true;
    await handler.handle();
    signedIn = false;
    await handler.handle();

    expect(repository.trace, [
      'refresh-token',
      'register:start',
      'register:end',
      'deactivate',
      'delete-token',
    ]);
  });
}
