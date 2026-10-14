import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';
import 'package:sl_furnitures/core/notifications/installation_identity.dart';
import 'package:sl_furnitures/core/notifications/push_registration_coordinator.dart';

/// A Clerk session that becomes unusable once Clerk is signed out, mirroring
/// `ClerkAuthAdapter.getToken()` returning null after sign-out begins.
class _SessionGateway implements ClerkAuthGateway {
  bool signedIn = true;
  final String token = 'clerk-token';
  int tokenRequests = 0;
  int signOutCalls = 0;

  final _changes = StreamController<void>.broadcast();

  @override
  bool get actionRequired => false;

  @override
  Stream<void> get changes => _changes.stream;

  @override
  bool get isSignedIn => signedIn;

  @override
  String? get sessionId => signedIn ? 'sess_test' : null;

  @override
  Future<void> initialize() async {}

  @override
  Future<String?> getSessionToken() async {
    tokenRequests++;

    return signedIn ? token : null;
  }

  @override
  Future<void> signOut() async {
    signOutCalls++;
    signedIn = false;
    _changes.add(null);
  }

  @override
  void terminate() => _changes.close();
}

/// Resolves a bearer token exactly as the API client does for an authenticated
/// request, so a retirement that runs after the session is blocked is visible
/// as a null token rather than passing silently.
class _BearerCheckedRepository implements DeviceRegistrationRepository {
  _BearerCheckedRepository(this._gateway);

  final ClerkAuthGateway _gateway;
  final List<String> deactivations = [];
  final List<String?> tokensDuringDeactivate = [];

  @override
  Future<void> deactivate(
    String deviceId, {
    RequestCancellation? cancellation,
  }) async {
    deactivations.add(deviceId);
    tokensDuringDeactivate.add(await _gateway.getSessionToken());
  }

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    return RegisteredDevice(
      id: 'dev_1',
      platform: 'ANDROID',
      appInstanceId: appInstanceId,
      status: 'ACTIVE',
    );
  }
}

class _Identity implements InstallationIdentityStore {
  @override
  Future<String> getOrCreate() async => 'installation-abc';
}

void main() {
  late _SessionGateway gateway;
  late _BearerCheckedRepository repository;
  late int tokenDeletions;
  late ClerkAuthAdapter adapter;

  PushRegistrationCoordinator build() => PushRegistrationCoordinator(
    repository: repository,
    tokenProvider: () async => 'fcm-token',
    tokenDeleter: () async => tokenDeletions++,
    installationIdentity: _Identity(),
  );

  setUp(() async {
    gateway = _SessionGateway();
    repository = _BearerCheckedRepository(gateway);
    tokenDeletions = 0;
    adapter = ClerkAuthAdapter(gateway: gateway);
    await adapter.initialize();
  });

  tearDown(() => adapter.dispose());

  test(
    'sign-out retires the device while the outgoing session still has a token',
    () async {
      final coordinator = build();
      await coordinator.registerForCustomer();
      adapter.addBeforeSignOutListener(coordinator.retireOnSignOut);

      await adapter.signOut();

      expect(repository.deactivations, ['dev_1']);
      expect(repository.tokensDuringDeactivate, ['clerk-token']);
      expect(tokenDeletions, 1);
      expect(gateway.signOutCalls, 1);
    },
  );

  test(
    'without the pre sign-out hook the retirement cannot authenticate',
    () async {
      final coordinator = build();
      await coordinator.registerForCustomer();
      adapter.addListener(() => unawaited(coordinator.retireOnSignOut()));

      await adapter.signOut();
      await Future<void>.delayed(Duration.zero);

      expect(repository.tokensDuringDeactivate, [isNull]);
    },
  );

  test(
    'a sign-out with no registered device deletes only the local token',
    () async {
      adapter.addBeforeSignOutListener(build().retireOnSignOut);

      await adapter.signOut();

      expect(repository.deactivations, isEmpty);
      expect(tokenDeletions, 1);
    },
  );
}
