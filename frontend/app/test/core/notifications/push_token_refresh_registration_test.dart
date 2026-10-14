import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';
import 'package:sl_furnitures/core/notifications/installation_identity.dart';
import 'package:sl_furnitures/core/notifications/push_registration_coordinator.dart';

/// A repository that can hold a registration open, so a token rotation can be
/// made to arrive while a request is already in flight.
class _GatedRepository implements DeviceRegistrationRepository {
  final List<String> sentTokens = [];
  Completer<void>? gate;

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    await gate?.future;
    sentTokens.add(token);

    return RegisteredDevice(
      id: 'dev_1',
      platform: 'ANDROID',
      appInstanceId: appInstanceId,
      status: 'ACTIVE',
    );
  }

  @override
  Future<void> deactivate(
    String deviceId, {
    RequestCancellation? cancellation,
  }) async {}
}

class _Identity implements InstallationIdentityStore {
  @override
  Future<String> getOrCreate() async => 'installation-abc';
}

/// A token that rotates while a registration is open must still reach the
/// server.
///
/// FCM rotates tokens independently of session events, so a rotation can land
/// while the startup registration is awaiting the network. Dropping it leaves
/// the server addressing a token the device no longer holds, and push stops
/// silently for that device.
void main() {
  late _GatedRepository repository;
  late String token;
  late PushRegistrationCoordinator coordinator;

  setUp(() {
    repository = _GatedRepository();
    token = 'token-OLD';
    coordinator = PushRegistrationCoordinator(
      repository: repository,
      tokenProvider: () async => token,
      tokenDeleter: () async {},
      installationIdentity: _Identity(),
    );
  });

  test('a token rotated during an in-flight registration is not dropped', () async {
    repository.gate = Completer<void>();

    final inFlight = coordinator.registerForCustomer();
    await Future<void>.delayed(Duration.zero);

    // Firebase rotates the token while that request is still open.
    token = 'token-NEW';
    final duringRefresh = await coordinator.registerForCustomer();
    expect(duringRefresh, isFalse, reason: 'the call is collapsed while busy');

    repository.gate!.complete();
    await inFlight;
    await Future<void>.delayed(Duration.zero);

    expect(repository.sentTokens, ['token-OLD', 'token-NEW']);
  });

  test('several rotations during one registration all converge', () async {
    repository.gate = Completer<void>();

    final inFlight = coordinator.registerForCustomer();
    await Future<void>.delayed(Duration.zero);

    token = 'token-B';
    unawaited(coordinator.registerForCustomer());
    token = 'token-C';
    unawaited(coordinator.registerForCustomer());

    repository.gate!.complete();
    await inFlight;
    await Future<void>.delayed(Duration.zero);

    // Only the latest token matters; the server must not be left holding one
    // the device has already discarded.
    expect(repository.sentTokens.last, 'token-C');
  });

  test('a rotation during an in-flight registration reports the final outcome',
      () async {
    repository.gate = Completer<void>();

    final inFlight = coordinator.registerForCustomer();
    await Future<void>.delayed(Duration.zero);
    token = 'token-NEW';
    unawaited(coordinator.registerForCustomer());

    repository.gate!.complete();

    // The returned value reflects the attempt that actually settled last, not
    // the superseded one.
    expect(await inFlight, isTrue);
  });

  test('no registration is pending when nothing rotates', () async {
    await coordinator.registerForCustomer();
    await Future<void>.delayed(Duration.zero);

    expect(repository.sentTokens, ['token-OLD']);
  });

  test('a collapsed call does not re-send an unchanged token', () async {
    repository.gate = Completer<void>();

    final inFlight = coordinator.registerForCustomer();
    await Future<void>.delayed(Duration.zero);
    unawaited(coordinator.registerForCustomer());
    unawaited(coordinator.registerForCustomer());

    repository.gate!.complete();
    await inFlight;
    await Future<void>.delayed(Duration.zero);

    // The server already holds this token, so the follow-up is skipped.
    expect(repository.sentTokens, ['token-OLD']);
  });

  test('a collapsed call retries when the settled attempt failed', () async {
    final attempts = <String>[];
    var failNext = true;
    final flaky = PushRegistrationCoordinator(
      repository: _FlakyRepository(attempts, () => failNext),
      tokenProvider: () async => token,
      tokenDeleter: () async {},
      installationIdentity: _Identity(),
    );

    unawaited(flaky.registerForCustomer());
    // Let the first attempt fail before asking for another.
    await Future<void>.delayed(Duration.zero);
    failNext = false;
    final retried = await flaky.registerForCustomer();

    expect(retried, isTrue);
    expect(attempts.length, greaterThan(1));
  });
}

class _FlakyRepository implements DeviceRegistrationRepository {
  _FlakyRepository(this.attempts, this.shouldFail);

  final List<String> attempts;
  final bool Function() shouldFail;

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    attempts.add(token);
    if (shouldFail()) {
      throw StateError('network down');
    }

    return RegisteredDevice(
      id: 'dev_1',
      platform: 'ANDROID',
      appInstanceId: appInstanceId,
      status: 'ACTIVE',
    );
  }

  @override
  Future<void> deactivate(
    String deviceId, {
    RequestCancellation? cancellation,
  }) async {}
}
