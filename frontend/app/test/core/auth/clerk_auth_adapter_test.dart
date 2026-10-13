import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/core/auth/secure_clerk_persistor.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_code.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';

void main() {
  group('SecureClerkPersistor', () {
    test(
      'round trips strings and JSON objects through secure storage',
      () async {
        final store = _FakeSecureStore();
        final persistor = SecureClerkPersistor(store: store);

        await persistor.write('client', <String, Object>{'signedIn': true});
        await persistor.write('token', 'session-token');

        expect(await persistor.read<Map<String, dynamic>>('client'), {
          'signedIn': true,
        });
        expect(await persistor.read<String>('token'), 'session-token');
      },
    );

    test('deletes persisted values', () async {
      final store = _FakeSecureStore();
      final persistor = SecureClerkPersistor(store: store);

      await persistor.write('client', 'value');
      await persistor.delete('client');

      expect(await persistor.read<String>('client'), isNull);
    });

    test('deletes malformed JSON during restoration', () async {
      final store = _FakeSecureStore()..values['client'] = '{malformed';
      final persistor = SecureClerkPersistor(store: store);

      expect(await persistor.read<Map<String, dynamic>>('client'), isNull);
      expect(store.values, isEmpty);
      expect(store.deleteCalls, 1);
    });

    test('preserves secure-storage read failures', () async {
      final store = _FakeSecureStore()..readError = StateError('unavailable');
      final sink = InMemoryDiagnosticSink();
      final persistor = SecureClerkPersistor(
        store: store,
        diagnostics: DefaultAppDiagnostics(enabled: true, sink: sink),
      );

      await expectLater(
        persistor.read<String>('client'),
        throwsA(isA<StateError>()),
      );
      expect(store.deleteCalls, 0);
      expect(sink.events.single.code, DiagnosticCode.securePersistenceFailed);
    });
  });

  group('ClerkAuthAdapter', () {
    test(
      'records session restoration failures without raw SDK details',
      () async {
        final sink = InMemoryDiagnosticSink();
        final adapter = ClerkAuthAdapter(
          gateway: _FakeGateway(initializeError: StateError('private detail')),
          diagnostics: DefaultAppDiagnostics(enabled: true, sink: sink),
        );

        await expectLater(adapter.initialize(), throwsStateError);

        expect(
          sink.events.single.code,
          DiagnosticCode.authSessionRestoreFailed,
        );
        expect(
          sink.events.single.toJson().toString(),
          isNot(contains('private')),
        );
        adapter.dispose();
      },
    );

    test(
      'records sign-out failures without identity or exception details',
      () async {
        final sink = InMemoryDiagnosticSink();
        final adapter = ClerkAuthAdapter(
          gateway: _FakeGateway(
            signedIn: true,
            signOutError: StateError('private sign-out detail'),
          ),
          diagnostics: DefaultAppDiagnostics(enabled: true, sink: sink),
        );
        await adapter.initialize();

        await expectLater(adapter.signOut(), throwsStateError);

        expect(sink.events.single.code, DiagnosticCode.authSignOutFailed);
        expect(
          sink.events.single.toJson().toString(),
          isNot(contains('private')),
        );
        adapter.dispose();
      },
    );

    test('returns no token when signed out', () async {
      final gateway = _FakeGateway();
      final adapter = ClerkAuthAdapter(gateway: gateway);

      expect(await adapter.getToken(), isNull);
      adapter.dispose();
    });

    test('unavailable fallback can be disposed safely', () {
      final adapter = ClerkAuthAdapter.unavailable();

      expect(adapter.dispose, returnsNormally);
    });

    test(
      'publishes a status change when Clerk signs the customer in',
      () async {
        // Clerk's prebuilt UI signs the customer in directly on its own state
        // object. The adapter only learns about it if the gateway republishes
        // every Clerk transition, so this is the sign-in path the customer
        // actually takes.
        final gateway = _FakeGateway();
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();
        expect(adapter.status, ClerkAuthStatus.signedOut);

        gateway.signedIn = true;
        await gateway.emitChange();

        expect(adapter.status, ClerkAuthStatus.signedIn);
        adapter.dispose();
      },
    );

    test('publishes a status change when Clerk signs the customer out', () async {
      final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();
      expect(adapter.status, ClerkAuthStatus.signedIn);

      gateway.signedIn = false;
      await gateway.emitChange();

      expect(adapter.status, ClerkAuthStatus.signedOut);
      expect(await adapter.getToken(), isNull);
      adapter.dispose();
    });

    test('resolves an in-flight flow as signed out, not as action required', () async {
      // A live sign-in or sign-up is a state the customer is already acting
      // on. Reporting it as an unresolved session disabled the account action
      // and diverted protected routes away from the authentication screen.
      final gateway = _FakeGateway();
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();

      expect(gateway.flowInProgress, isFalse);
      gateway.flowInProgress = true;
      await gateway.emitChange();

      expect(adapter.status, ClerkAuthStatus.signedOut);
      adapter.dispose();
    });

    test('reports action required only for an unresolved session', () async {
      final gateway = _FakeGateway();
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();

      gateway.flowInProgress = true;
      expect(adapter.status, ClerkAuthStatus.signedOut);

      gateway.sessionPending = true;
      await gateway.emitChange();
      expect(adapter.status, ClerkAuthStatus.actionRequired);

      adapter.dispose();
    });

    test('returns the current Clerk session token when signed in', () async {
      final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();

      expect(await adapter.getToken(), 'clerk-token');
      expect(adapter.status, ClerkAuthStatus.signedIn);
      adapter.dispose();
    });

    test('sign out delegates to Clerk and clears local status', () async {
      final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();

      await adapter.signOut();

      expect(gateway.signOutCalls, 1);
      expect(adapter.status, ClerkAuthStatus.signedOut);
      expect(await adapter.getToken(), isNull);
      adapter.dispose();
    });

    test(
      'restores a later gateway sign-in after successful sign out',
      () async {
        final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();

        await adapter.signOut();
        gateway.signedIn = true;
        await gateway.emitChange();

        expect(adapter.status, ClerkAuthStatus.signedIn);
        adapter.dispose();
      },
    );

    test(
      'keeps failed sign out blocked from later stale gateway changes',
      () async {
        final gateway = _FakeGateway(
          signedIn: true,
          token: 'clerk-token',
          signOutError: StateError('sign out failed'),
        );
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();

        await expectLater(adapter.signOut(), throwsStateError);
        await gateway.emitChange();

        expect(adapter.status, ClerkAuthStatus.signedOut);
        expect(await adapter.getToken(), isNull);
        adapter.dispose();
      },
    );

    test('initialization does not clear a previous sign-out block', () async {
      final gateway = _FakeGateway(
        signedIn: true,
        token: 'clerk-token',
        signOutError: StateError('sign out failed'),
      );
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();
      await expectLater(adapter.signOut(), throwsStateError);

      gateway.signedIn = true;
      await adapter.initialize();

      expect(adapter.status, ClerkAuthStatus.signedOut);
      expect(await adapter.getToken(), isNull);
      adapter.dispose();
    });

    test(
      'runs pre sign-out cleanup while the session token is still obtainable',
      () async {
        final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();
        String? tokenDuringCleanup;
        ClerkAuthStatus? statusDuringCleanup;
        adapter.addBeforeSignOutListener(() async {
          tokenDuringCleanup = await adapter.getToken();
          statusDuringCleanup = adapter.status;
          expect(gateway.signOutCalls, 0);
        });

        await adapter.signOut();

        expect(tokenDuringCleanup, 'clerk-token');
        expect(statusDuringCleanup, ClerkAuthStatus.signedIn);
        expect(gateway.signOutCalls, 1);
        expect(await adapter.getToken(), isNull);
        adapter.dispose();
      },
    );

    test(
      'signs out even when pre sign-out cleanup fails',
      () async {
        final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();
        adapter.addBeforeSignOutListener(
          () async => throw StateError('offline'),
        );

        await adapter.signOut();

        expect(gateway.signOutCalls, 1);
        expect(adapter.status, ClerkAuthStatus.signedOut);
        adapter.dispose();
      },
    );

    test('does not run removed pre sign-out cleanup', () async {
      final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();
      var cleanupCalls = 0;
      Future<void> cleanup() async {
        cleanupCalls++;
      }

      adapter.addBeforeSignOutListener(cleanup);
      adapter.removeBeforeSignOutListener(cleanup);

      await adapter.signOut();

      expect(cleanupCalls, 0);
      expect(gateway.signOutCalls, 1);
      adapter.dispose();
    });

    test(
      'a stalled pre sign-out listener does not block sign-out',
      () async {
        final gateway = _FakeGateway(signedIn: true, token: 'clerk-token');
        final adapter = ClerkAuthAdapter(gateway: gateway);
        await adapter.initialize();
        var laterListenerRan = false;
        adapter.addBeforeSignOutListener(() => Completer<void>().future);
        adapter.addBeforeSignOutListener(() async => laterListenerRan = true);

        await adapter.signOut();

        expect(gateway.signOutCalls, 1);
        expect(adapter.status, ClerkAuthStatus.signedOut);
        expect(laterListenerRan, isTrue);
        adapter.dispose();
      },
      timeout: const Timeout(Duration(seconds: 60)),
    );

    test('rejects a token that completes after sign out starts', () async {
      final delayedToken = Completer<String?>();
      final gateway = _FakeGateway(signedIn: true, delayedToken: delayedToken);
      final adapter = ClerkAuthAdapter(gateway: gateway);
      await adapter.initialize();

      final tokenFuture = adapter.getToken();
      await Future<void>.delayed(Duration.zero);
      final signOutFuture = adapter.signOut();
      delayedToken.complete('stale-clerk-token');

      expect(await tokenFuture, isNull);
      await signOutFuture;
      expect(adapter.status, ClerkAuthStatus.signedOut);
      adapter.dispose();
    });
  });
}

class _FakeSecureStore implements SecureKeyValueStore {
  final values = <String, String>{};
  Object? readError;
  int deleteCalls = 0;

  @override
  Future<String?> read(String key) async {
    if (readError case final error?) throw error;
    return values[key];
  }

  @override
  Future<void> write(String key, String value) async => values[key] = value;

  @override
  Future<void> delete(String key) async {
    deleteCalls++;
    values.remove(key);
  }
}

class _FakeGateway implements ClerkAuthGateway {
  _FakeGateway({
    this.signedIn = false,
    this.token,
    this.delayedToken,
    this.signOutError,
    this.initializeError,
  });

  bool signedIn;
  final String? token;
  final Completer<String?>? delayedToken;
  final Object? signOutError;
  final Object? initializeError;
  int signOutCalls = 0;

  /// Mirrors a live Clerk sign-in or sign-up attempt.
  bool flowInProgress = false;

  /// Mirrors a session Clerk holds but cannot activate yet.
  bool sessionPending = false;

  final _changes = StreamController<void>.broadcast();

  @override
  Stream<void> get changes => _changes.stream;

  @override
  bool get actionRequired => sessionPending;

  @override
  bool get isSignedIn => signedIn;

  @override
  Future<void> initialize() async {
    if (initializeError case final error?) throw error;
  }

  @override
  Future<String?> getSessionToken() async =>
      delayedToken == null ? token : delayedToken!.future;

  @override
  Future<void> signOut() async {
    signOutCalls++;
    if (signOutError case final error?) throw error;
    signedIn = false;
    _changes.add(null);
  }

  Future<void> emitChange() async {
    _changes.add(null);
    await Future<void>.delayed(Duration.zero);
  }

  @override
  void terminate() => _changes.close();
}
