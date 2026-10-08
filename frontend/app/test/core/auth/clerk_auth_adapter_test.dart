import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/core/auth/secure_clerk_persistor.dart';

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
      final persistor = SecureClerkPersistor(store: store);

      await expectLater(
        persistor.read<String>('client'),
        throwsA(isA<StateError>()),
      );
      expect(store.deleteCalls, 0);
    });
  });

  group('ClerkAuthAdapter', () {
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
  });

  bool signedIn;
  final String? token;
  final Completer<String?>? delayedToken;
  final Object? signOutError;
  int signOutCalls = 0;
  final _changes = StreamController<void>.broadcast();

  @override
  Stream<void> get changes => _changes.stream;

  @override
  bool get actionRequired => false;

  @override
  bool get isSignedIn => signedIn;

  @override
  Future<void> initialize() async {}

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
