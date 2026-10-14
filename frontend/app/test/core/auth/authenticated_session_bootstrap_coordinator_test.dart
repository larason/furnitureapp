import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/auth/authenticated_session_bootstrap_coordinator.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/features/account/data/customer_profile.dart';
import 'package:sl_furnitures/features/account/data/profile_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('waits for an eligible session and bootstraps it once', () async {
    final session = _Session();
    final profiles = _ProfileRepository();
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
    )..start();

    expect(profiles.calls, 0);
    session.signIn('sess_a');
    await _settle();
    session.emit();
    await _settle();

    expect(profiles.calls, 1);
    coordinator.dispose();
  });

  test('retries a transient bootstrap failure with bounded backoff', () async {
    final session = _Session()..signIn('sess_a');
    final profiles = _ProfileRepository(
      failures: <Object>[
        const ApiTransportException(kind: ApiTransportFailureKind.connection),
      ],
    );
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
      retryDelays: const <Duration>[Duration.zero],
    )..start();

    await _settle();
    await _settle();

    expect(profiles.calls, 2);
    coordinator.dispose();
  });

  test('starts a new bounded retry cycle when the app resumes', () async {
    final session = _Session()..signIn('sess_a');
    final profiles = _ProfileRepository(
      failures: <Object>[
        const ApiTransportException(kind: ApiTransportFailureKind.connection),
        const ApiTransportException(kind: ApiTransportFailureKind.connection),
      ],
    );
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
      retryDelays: const <Duration>[Duration.zero],
    )..start();

    await _settle();
    await _settle();
    coordinator.didChangeAppLifecycleState(AppLifecycleState.resumed);
    await _settle();

    expect(profiles.calls, 3);
    coordinator.dispose();
  });

  test('an auth re-emission replaces a pending retry timer', () async {
    final session = _Session()..signIn('sess_a');
    final profiles = _ProfileRepository(
      failures: <Object>[
        const ApiTransportException(kind: ApiTransportFailureKind.connection),
      ],
    );
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
      retryDelays: const <Duration>[Duration(days: 1)],
    )..start();

    await _settle();
    session.emit();
    await _settle();

    expect(profiles.calls, 2);
    coordinator.dispose();
  });

  test(
    'does not retry a non-transient failure after foreground resume',
    () async {
      final session = _Session()..signIn('sess_a');
      final profiles = _ProfileRepository(
        failures: <Object>[
          const ApiError(statusCode: 401, errors: <ApiErrorItem>[]),
        ],
      );
      final coordinator = AuthenticatedSessionBootstrapCoordinator(
        session: session,
        profiles: profiles,
      )..start();

      await _settle();
      coordinator.didChangeAppLifecycleState(AppLifecycleState.resumed);
      await _settle();

      expect(profiles.calls, 1);
      coordinator.dispose();
    },
  );

  test('does not consume retries when foregrounded during a request', () async {
    final session = _Session()..signIn('sess_a');
    final profiles = _ProfileRepository(gated: true);
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
      retryDelays: const <Duration>[Duration.zero, Duration.zero],
    )..start();

    await _settle();
    coordinator.didChangeAppLifecycleState(AppLifecycleState.resumed);
    coordinator.didChangeAppLifecycleState(AppLifecycleState.resumed);
    profiles.releaseError(
      0,
      const ApiTransportException(kind: ApiTransportFailureKind.connection),
    );
    await _settle();
    await _settle();

    expect(profiles.calls, 2);
    coordinator.dispose();
  });

  test(
    'retries the readiness callback before marking the session ready',
    () async {
      final session = _Session()..signIn('sess_a');
      final profiles = _ProfileRepository();
      var callbackCalls = 0;
      final coordinator = AuthenticatedSessionBootstrapCoordinator(
        session: session,
        profiles: profiles,
        retryDelays: const <Duration>[Duration.zero],
        onSessionReady: (_) {
          callbackCalls++;
          if (callbackCalls == 1) throw StateError('callback failed');
        },
      )..start();

      await _settle();
      await _settle();

      expect(profiles.calls, 2);
      expect(callbackCalls, 2);
      coordinator.dispose();
    },
  );

  test('ignores a stale profile result after an account switch', () async {
    final session = _Session()..signIn('sess_a');
    final profiles = _ProfileRepository(gated: true);
    final ready = <String>[];
    final coordinator = AuthenticatedSessionBootstrapCoordinator(
      session: session,
      profiles: profiles,
      onSessionReady: ready.add,
    )..start();

    await _settle();
    session.signIn('sess_b');
    await _settle();
    profiles.release(0);
    await _settle();
    expect(ready, isEmpty);

    profiles.release(1);
    await _settle();

    expect(ready, <String>['sess_b']);
    coordinator.dispose();
  });
}

Future<void> _settle() => Future<void>.delayed(Duration.zero);

class _Session extends ChangeNotifier implements AuthSession {
  ClerkAuthStatus _status = ClerkAuthStatus.signedOut;
  String? _sessionId;

  @override
  ClerkAuthStatus get status => _status;

  @override
  bool get isSignedIn => _status == ClerkAuthStatus.signedIn;

  @override
  String? get sessionId => _sessionId;

  void signIn(String sessionId) {
    _status = ClerkAuthStatus.signedIn;
    _sessionId = sessionId;
    notifyListeners();
  }

  void emit() => notifyListeners();

  @override
  Future<String?> getToken() async => 'token';

  @override
  void addBeforeSignOutListener(Future<void> Function() listener) {}

  @override
  void removeBeforeSignOutListener(Future<void> Function() listener) {}

  @override
  Future<void> signOut() async {
    _status = ClerkAuthStatus.signedOut;
    _sessionId = null;
    notifyListeners();
  }
}

class _ProfileRepository implements ProfileRepository {
  _ProfileRepository({this.failures = const <Object>[], this.gated = false});

  final List<Object> failures;
  final bool gated;
  final List<Completer<CustomerProfile>> _pending = [];
  int calls = 0;

  @override
  Future<CustomerProfile> getProfile() {
    final call = calls++;
    if (call < failures.length) {
      return Future<CustomerProfile>.error(failures[call]);
    }
    if (!gated) return Future<CustomerProfile>.value(_profile);
    final pending = Completer<CustomerProfile>();
    _pending.add(pending);
    return pending.future;
  }

  void release(int index) => _pending[index].complete(_profile);

  void releaseError(int index, Object error) =>
      _pending[index].completeError(error);

  @override
  Future<CustomerProfile> updateProfile({
    String? name,
    String? phone,
    bool clearPhone = false,
  }) => throw UnimplementedError();
}

const _profile = CustomerProfile(
  id: 'user_01',
  role: 'CUSTOMER',
  name: null,
  email: 'customer@example.test',
  phone: null,
  emailVerified: true,
);
