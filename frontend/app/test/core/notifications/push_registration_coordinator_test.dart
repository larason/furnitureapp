import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/secure_clerk_persistor.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';
import 'package:sl_furnitures/core/notifications/installation_identity.dart';
import 'package:sl_furnitures/core/notifications/push_registration_coordinator.dart';

/// In-memory stand-in for the persisted installation identity.
class _FakeInstallationIdentityStore implements InstallationIdentityStore {
  _FakeInstallationIdentityStore();

  static const value = 'installation-abc';

  int createCount = 0;

  @override
  Future<String> getOrCreate() async {
    createCount++;

    return value;
  }
}

/// Secure storage that never persists, to exercise the real implementation.
class _VolatileStore implements SecureKeyValueStore {
  final Map<String, String> values = {};

  @override
  Future<void> delete(String key) async => values.remove(key);

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;
}

/// Secure storage that always fails, to prove registration is not blocked.
class _FailingStore implements SecureKeyValueStore {
  @override
  Future<void> delete(String key) async => throw StateError('unavailable');

  @override
  Future<String?> read(String key) async => throw StateError('unavailable');

  @override
  Future<void> write(String key, String value) async =>
      throw StateError('unavailable');
}

/// Records what the coordinator asked the server to do.
class _RecordingRepository implements DeviceRegistrationRepository {
  _RecordingRepository();

  final List<Map<String, String>> registrations = [];
  final List<String> deactivations = [];
  Object? registrationError;
  Object? deactivationError;
  int counter = 0;

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    if (registrationError != null) {
      throw registrationError!;
    }
    registrations.add({'token': token, 'app_instance_id': appInstanceId});

    return RegisteredDevice(
      id: 'dev_${++counter}',
      platform: 'ANDROID',
      appInstanceId: appInstanceId,
      status: 'ACTIVE',
    );
  }

  @override
  Future<void> deactivate(String deviceId, {RequestCancellation? cancellation}) async {
    if (deactivationError != null) {
      throw deactivationError!;
    }
    deactivations.add(deviceId);
  }
}

void main() {
  late _RecordingRepository repository;
  late _FakeInstallationIdentityStore identity;
  late List<String> tokenEvents;
  late bool tokenAvailable;
  late int deleteCount;

  PushRegistrationCoordinator build() => PushRegistrationCoordinator(
        repository: repository,
        tokenProvider: () async => tokenAvailable ? 'current-token' : null,
        tokenDeleter: () async {
          deleteCount++;
          tokenEvents.add('deleted');
        },
        installationIdentity: identity,
      );

  setUp(() {
    repository = _RecordingRepository();
    identity = _FakeInstallationIdentityStore();
    tokenEvents = [];
    tokenAvailable = true;
    deleteCount = 0;
  });

  test('registers the current token for the signed-in customer', () async {
    final registered = await build().registerForCustomer();

    expect(registered, isTrue);
    expect(repository.registrations, [
      {'token': 'current-token', 'app_instance_id': 'installation-abc'},
    ]);
  });

  test('remembers the server device id so it can be retired later', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();

    expect(coordinator.registeredDeviceId, 'dev_1');
  });

  test('does not register when no token is available', () async {
    tokenAvailable = false;

    final registered = await build().registerForCustomer();

    expect(registered, isFalse);
    expect(repository.registrations, isEmpty);
  });

  test('rotating the token converges on one device per installation', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    tokenEvents.add('rotated');
    await coordinator.registerForCustomer();

    expect(repository.registrations.length, 2);
    expect(
      repository.registrations.map((r) => r['app_instance_id']).toSet(),
      {'installation-abc'},
    );
    expect(coordinator.registeredDeviceId, 'dev_2');
  });

  test('never sends the registration token as the installation identity', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    tokenEvents.add('rotated');
    await coordinator.registerForCustomer();

    expect(
      repository.registrations.every(
        (r) => r['app_instance_id'] != r['token'],
      ),
      isTrue,
      reason: 'The token is secret-adjacent and rotates; it must never be the identity.',
    );
    expect(
      repository.registrations.every(
        (r) => r['app_instance_id'] == 'installation-abc',
      ),
      isTrue,
    );
  });

  test('rotations reuse the same persisted identity', () async {
    final store = _VolatileStore();
    final persisted = SecureStorageInstallationIdentityStore(store);
    var token = 'token-1';
    final coordinator = PushRegistrationCoordinator(
      repository: repository,
      tokenProvider: () async => token,
      tokenDeleter: () async {},
      installationIdentity: persisted,
    );

    await coordinator.registerForCustomer();
    token = 'token-2';
    await coordinator.registerForCustomer();
    token = 'token-3';
    await coordinator.registerForCustomer();

    // Every rotation must land on one identity, otherwise the server creates a
    // new active device row per rotation.
    final ids = repository.registrations
        .map((r) => r['app_instance_id'])
        .toSet();
    expect(ids.length, 1);
    expect(repository.registrations.length, 3);
  });

  test('the installation identity is generated once and reused', () async {
    final store = _VolatileStore();
    final persisted = SecureStorageInstallationIdentityStore(store);

    final first = await persisted.getOrCreate();
    final second = await persisted.getOrCreate();

    expect(first, isNotEmpty);
    expect(second, first);
    expect(store.values[SecureStorageInstallationIdentityStore.storageKey], first);
  });

  test('a generated identity looks like a uuid and is not the token', () async {
    final store = _VolatileStore();
    final generated = await SecureStorageInstallationIdentityStore(store)
        .getOrCreate();

    expect(
      RegExp(
        r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
      ).hasMatch(generated),
      isTrue,
      reason: 'Expected an RFC 4122 v4 identifier, got $generated',
    );
  });

  test('two installations get distinct identities', () {
    final first = generateInstallationId();
    final second = generateInstallationId();

    expect(first, isNot(second));
  });

  test('an unavailable store still yields a usable identity', () async {
    final persisted = SecureStorageInstallationIdentityStore(
      _FailingStore(),
    );

    final identity = await persisted.getOrCreate();

    expect(identity, isNotEmpty);
  });

  test('sign-out deactivates the device before deleting the local token', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    tokenEvents.clear();

    await coordinator.retireOnSignOut();

    expect(repository.deactivations, ['dev_1']);
    expect(tokenEvents, ['deleted']);
    expect(coordinator.registeredDeviceId, isNull);
  });

  test('a failed deactivation still discards the local token', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    repository.deactivationError = StateError('offline');
    tokenEvents.clear();

    await coordinator.retireOnSignOut();

    expect(deleteCount, 1);
    expect(coordinator.registeredDeviceId, isNull);
  });

  test('a failed local token deletion does not throw', () async {
    final coordinator = PushRegistrationCoordinator(
      repository: repository,
      tokenProvider: () async => 'current-token',
      tokenDeleter: () async => throw StateError('offline'),
      installationIdentity: identity,
    );
    await coordinator.registerForCustomer();

    await expectLater(coordinator.retireOnSignOut(), completes);
  });

  test('registration failure is contained and leaves no device id', () async {
    final coordinator = build();
    repository.registrationError = StateError('offline');

    final registered = await coordinator.registerForCustomer();

    expect(registered, isFalse);
    expect(coordinator.registeredDeviceId, isNull);
  });

  test('sign-out with no prior registration still deletes the local token', () async {
    await build().retireOnSignOut();

    expect(repository.deactivations, isEmpty);
    expect(deleteCount, 1);
  });

  test('account switch retires the previous device before registering', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    final firstDevice = coordinator.registeredDeviceId;
    tokenEvents.clear();

    var refreshed = false;
    await coordinator.reconcileSession(
      isSignedIn: true,
      wasSignedIn: true,
      tokenRefresher: () async => refreshed = true,
    );

    expect(repository.deactivations, [firstDevice]);
    expect(tokenEvents, ['deleted']);
    expect(refreshed, isTrue);
    expect(repository.registrations.length, 2);
    expect(coordinator.registeredDeviceId, isNot(firstDevice));
  });

  test('first sign-in registers without retiring anything', () async {
    final coordinator = build();
    var refreshed = false;

    await coordinator.reconcileSession(
      isSignedIn: true,
      wasSignedIn: false,
      tokenRefresher: () async => refreshed = true,
    );

    expect(refreshed, isTrue);
    expect(repository.deactivations, isEmpty);
    expect(deleteCount, 0);
    expect(repository.registrations.length, 1);
  });

  test('sign-out retires the device and does not register', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();

    await coordinator.reconcileSession(
      isSignedIn: false,
      wasSignedIn: true,
    );

    expect(repository.deactivations, ['dev_1']);
    expect(repository.registrations.length, 1);
    expect(coordinator.registeredDeviceId, isNull);
  });

  test('sign-out when already signed out changes nothing', () async {
    final coordinator = build();

    await coordinator.reconcileSession(isSignedIn: false, wasSignedIn: false);

    expect(repository.deactivations, isEmpty);
    expect(deleteCount, 0);
  });

  test('a failed retirement still discards the local token before re-registration', () async {
    final coordinator = build();
    await coordinator.registerForCustomer();
    repository.deactivationError = StateError('offline');

    await coordinator.reconcileSession(
      isSignedIn: true,
      wasSignedIn: true,
    );

    // Registration is still attempted, but the local token was discarded first,
    // so the new account cannot silently inherit the previous association.
    expect(repository.deactivations, isEmpty);
    expect(deleteCount, 1);
    expect(repository.registrations.length, 2);
    expect(repository.registrations.last['token'], 'current-token');
  });

  test('re-entrant registration does not issue duplicate calls', () async {
    final coordinator = build();

    await Future.wait([
      coordinator.registerForCustomer(),
      coordinator.registerForCustomer(),
    ]);

    expect(repository.registrations.length, 1);
  });
}

