import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';
import 'package:sl_furnitures/core/notifications/installation_identity.dart';
import 'package:sl_furnitures/core/notifications/push_registration_coordinator.dart';

/// A repository that reports which customer the server now associates the
/// token with, mirroring the server's single-ACTIVE-owner rule.
class _OwnershipRepository implements DeviceRegistrationRepository {
  final List<String> sentTokens = [];

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    sentTokens.add(token);

    return RegisteredDevice(
      id: 'dev_${sentTokens.length}',
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

void main() {
  late _OwnershipRepository repository;
  late String token;

  PushRegistrationCoordinator build() => PushRegistrationCoordinator(
        repository: repository,
        tokenProvider: () async => token,
        tokenDeleter: () async {},
        installationIdentity: _Identity(),
      );

  setUp(() {
    repository = _OwnershipRepository();
    token = 'shared-token';
  });

  test('sign-out clears the device id', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    expect(coordinator.registeredDeviceId, isNotNull);

    await coordinator.retireOnSignOut();

    expect(coordinator.registeredDeviceId, isNull);
  });

  test('the same token is registered again for a second account', () async {
    final a = build();
    expect(await a.registerForCustomer(), isTrue);
    await a.retireOnSignOut();

    final b = build();
    expect(await b.registerForCustomer(), isTrue);

    expect(repository.sentTokens, ['shared-token', 'shared-token']);
  });

  test('the second account is registered even in the same long-lived instance', () async {
    final coordinator = build();

    expect(await coordinator.registerForCustomer(), isTrue);
    await coordinator.retireOnSignOut();

    expect(await coordinator.registerForCustomer(), isTrue);

    // Two registrations for one token value: the server reassigned the device.
    expect(repository.sentTokens, ['shared-token', 'shared-token']);
  });

  test('a pending flag set before sign-out does not fire for the next account', () async {
    final coordinator = build();

    // Set a pending registration, then sign out before it drains.
    unawaited(coordinator.registerForCustomer());
    unawaited(coordinator.registerForCustomer());
    await Future<void>.delayed(Duration.zero);
    await coordinator.retireOnSignOut();
    await Future<void>.delayed(Duration.zero);

    final before = repository.sentTokens.length;
    expect(await coordinator.registerForCustomer(), isTrue);

    // Exactly the one new registration; nothing extra was emitted for the
    // account that had already signed out.
    expect(repository.sentTokens.length, before + 1);
  });
}
