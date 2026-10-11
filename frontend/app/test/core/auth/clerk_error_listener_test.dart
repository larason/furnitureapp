import 'dart:io';

import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/auth_failure_message.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_code.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';
import 'package:sl_furnitures/core/presentation/auth_failure_presenter.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

/// A failure carrying the SDK text that must never reach the screen.
final clerk.ClerkError _failure = clerk.ClerkError(
  code: clerk.ClerkErrorCode.noSessionTokenRetrieved,
  message: '{arg} (EXTERNAL ERROR)',
  argument: 'SocketException: 10.0.2.2:443 connection refused',
);

const String _sessionEndedCopy = 'Your session has ended. Please sign in again.';

void main() {
  group('presentAuthFailure', () {
    testWidgets('turns a rejected credential into customer-facing copy', (
      tester,
    ) async {
      await tester.pumpWidget(_host());
      await tester.pumpAndSettle();

      await _report(tester);

      expect(find.text(_sessionEndedCopy), findsOneWidget);
    });

    testWidgets('never renders the SDK error detail', (tester) async {
      await tester.pumpWidget(_host());
      await tester.pumpAndSettle();

      await _report(tester);

      final text = _snackBarText(tester);
      expect(text, isNot(contains('ERROR RECEIVED FROM SERVER')));
      expect(text, isNot(contains('EXTERNAL ERROR')));
      expect(text, isNot(contains('SocketException')));
      expect(text, isNot(contains('10.0.2.2')));
    });

    testWidgets('replaces a previous failure instead of stacking them', (
      tester,
    ) async {
      await tester.pumpWidget(_host());
      await tester.pumpAndSettle();

      await _report(tester);
      expect(find.byType(SnackBar), findsOneWidget);

      await _report(tester);
      expect(find.byType(SnackBar), findsOneWidget);
    });

    testWidgets('leaves the calling screen interactive', (tester) async {
      await tester.pumpWidget(_host());
      await tester.pumpAndSettle();

      await _report(tester);

      // The failure is additive rather than a blocking error page.
      expect(find.text('Home'), findsOneWidget);
      expect(find.byType(SnackBar), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('records a diagnostic without the SDK detail', (tester) async {
      final sink = InMemoryDiagnosticSink();
      final diagnostics = DefaultAppDiagnostics(enabled: true, sink: sink);
      await tester.pumpWidget(_host(diagnostics: diagnostics));
      await tester.pumpAndSettle();

      await _report(tester, diagnostics: diagnostics);

      expect(sink.events.single.code, DiagnosticCode.authFlowFailed);
      expect(sink.events.single.toJson().toString(), isNot(contains('Socket')));
    });

    testWidgets('does not throw without a messenger', (tester) async {
      // Guards the branch used if authentication fails before the navigator
      // is mounted.
      await tester.pumpWidget(
        Directionality(
          textDirection: TextDirection.ltr,
          child: Builder(
            builder: (context) {
              presentAuthFailure(context, _failure);
              return const SizedBox.shrink();
            },
          ),
        ),
      );

      expect(tester.takeException(), isNull);
    });

    testWidgets('falls back to general copy with no Clerk provider above', (
      tester,
    ) async {
      await tester.pumpWidget(_host());
      await tester.pumpAndSettle();

      await _report(tester);

      // No ClerkAuth ancestor here, so the flow cannot be inferred. The
      // session failure must still be explained rather than swallowed.
      expect(find.text(_sessionEndedCopy), findsOneWidget);
    });
  });

  group('presentAuthFailure flow selection', () {
    testWidgets('phrases a failure that only the sign-in flow resolves', (
      tester,
    ) async {
      // ClerkAuthState.create performs real async work, which must run outside
      // the fake-async zone that testWidgets installs.
      late ClerkAuthState auth;
      await tester.runAsync(() async {
        auth = await _createAuthState();
        auth.client = _clientWithSignIn(
          _signIn(status: clerk.Status.needsFirstFactor),
        );
      });
      addTearDown(auth.terminate);

      await tester.pumpWidget(_hostWithClerk(auth));
      await tester.pumpAndSettle();

      // An unmapped code reaches the per-flow fallback, so the message can only
      // appear if the flow was inferred as sign-in.
      await _report(
        tester,
        failure: clerk.ClerkError(
          code: clerk.ClerkErrorCode.unknownError,
          message: 'sign-in failed for a reason we do not recognise',
        ),
      );

      expect(
        find.text('We could not sign you in with those details. Please try again.'),
        findsOneWidget,
      );
      expect(
        find.text('Something went wrong. Please try again.'),
        findsNothing,
      );
    });

    testWidgets('phrases a code failure for the verification flow', (
      tester,
    ) async {
      late ClerkAuthState auth;
      await tester.runAsync(() async {
        auth = await _createAuthState();
        auth.client = _clientWithSignIn(
          _signIn(status: clerk.Status.needsSecondFactor),
        );
      });
      addTearDown(auth.terminate);

      await tester.pumpWidget(_hostWithClerk(auth));
      await tester.pumpAndSettle();

      await _report(
        tester,
        failure: clerk.ClerkError(
          code: clerk.ClerkErrorCode.noInitialCodeHasBeenSetUpToResend,
          message: 'no initial code has been set up to resend',
        ),
      );

      expect(find.text('Request a new code, then try again.'), findsOneWidget);
    });

    testWidgets('a verification-flow failure does not show sign-in copy', (
      tester,
    ) async {
      late ClerkAuthState auth;
      await tester.runAsync(() async {
        auth = await _createAuthState();
        auth.client = _clientWithSignIn(
          _signIn(
            status: clerk.Status.needsFirstFactor,
            strategy: clerk.Strategy.emailCode,
          ),
        );
      });
      addTearDown(auth.terminate);

      await tester.pumpWidget(_hostWithClerk(auth));
      await tester.pumpAndSettle();

      // A code problem must be phrased as one, not as a failed sign-in.
      await _report(
        tester,
        failure: clerk.ClerkError(
          code: clerk.ClerkErrorCode.noInitialCodeHasBeenSetUpToResend,
          message: 'no initial code has been set up to resend',
        ),
      );

      expect(find.text('Request a new code, then try again.'), findsOneWidget);
      expect(find.text('We could not sign you in with those details. Please try again.'), findsNothing);
    });
  });

  group('authFailureFlowFor', () {
    test('reports general when no flow is in progress', () async {
      expect(authFailureFlowFor(null), AuthFailureFlow.general);
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      // Clerk restores a persisted client on construction, which is how a
      // restored session works. Each case therefore states its own client
      // rather than relying on a fresh state being empty.
      auth.client = clerk.Client.empty;
      expect(authFailureFlowFor(auth), AuthFailureFlow.general);
    });

    test('reports general for a restored session with no flow', () async {
      // Proof that a session restored from storage does not look like an
      // in-progress flow, which would otherwise mis-phrase its failures.
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSession();

      expect(auth.isSignedIn, isFalse);
      expect(authFailureFlowFor(auth), AuthFailureFlow.general);
    });

    test('reports sign in for a sign-in awaiting a password', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignIn(
        _signIn(status: clerk.Status.needsFirstFactor),
      );

      // A password prompt is a sign-in step, not a code verification.
      expect(authFailureFlowFor(auth), AuthFailureFlow.signIn);
    });

    test('reports verification for a sign-in awaiting a second factor', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignIn(
        _signIn(
          status: clerk.Status.needsSecondFactor,
          strategy: clerk.Strategy.emailCode,
        ),
      );

      expect(authFailureFlowFor(auth), AuthFailureFlow.verification);
    });

    test('reports verification for a sign-in awaiting an email code', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignIn(
        _signIn(
          status: clerk.Status.needsFirstFactor,
          strategy: clerk.Strategy.emailCode,
        ),
      );

      expect(authFailureFlowFor(auth), AuthFailureFlow.verification);
    });

    test('reports recovery for a password reset in progress', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignIn(
        _signIn(
          status: clerk.Status.needsFirstFactor,
          strategy: clerk.Strategy.resetPasswordEmailCode,
        ),
      );

      expect(authFailureFlowFor(auth), AuthFailureFlow.recovery);
    });

    test('reports sign up for a sign-up still collecting requirements', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignUp(
        clerk.SignUp.fromJson(_signUpJson(verified: true)),
      );

      expect(authFailureFlowFor(auth), AuthFailureFlow.signUp);
    });

    test('reports verification for a sign-up awaiting a code', () async {
      final auth = await _createAuthState();
      addTearDown(auth.terminate);
      auth.client = _clientWithSignUp(
        clerk.SignUp.fromJson(_signUpJson(verified: false)),
      );

      expect(authFailureFlowFor(auth), AuthFailureFlow.verification);
    });
  });

  group('ClerkAuthState.handleError', () {
    test('rethrows a failure when nothing is listening', () async {
      // This is why the app root wraps its tree in a ClerkErrorListener: with
      // no subscriber the error escapes as an unhandled exception and the
      // customer is left on a spinner with no explanation.
      final authState = await _createAuthState();
      addTearDown(authState.terminate);

      expect(
        () => authState.handleError(_failure),
        throwsA(isA<clerk.ClerkError>()),
      );
    });

    test('delivers a failure to a subscriber instead of throwing', () async {
      final authState = await _createAuthState();
      addTearDown(authState.terminate);

      final received = <clerk.ClerkError>[];
      final subscription = authState.errorStream.listen(received.add);
      addTearDown(subscription.cancel);

      authState.handleError(_failure);
      await Future<void>.delayed(Duration.zero);

      expect(
        received.single.code,
        clerk.ClerkErrorCode.noSessionTokenRetrieved,
      );
    });

    test('keeps delivering failures across repeated attempts', () async {
      final authState = await _createAuthState();
      addTearDown(authState.terminate);

      final received = <clerk.ClerkError>[];
      final subscription = authState.errorStream.listen(received.add);
      addTearDown(subscription.cancel);

      // A customer who mistypes twice must be told twice; the listener is the
      // reason the second attempt is not silent.
      authState.handleError(_failure);
      authState.handleError(_failure);
      await Future<void>.delayed(Duration.zero);

      expect(received, hasLength(2));
    });
  });
}

Widget _host({AppDiagnostics diagnostics = const NoopAppDiagnostics()}) =>
    MaterialApp(
      theme: AppTheme.light(),
      home: Scaffold(
        body: Column(
          children: <Widget>[
            Builder(
              builder: (context) => TextButton(
                key: const ValueKey<String>('host.action'),
                onPressed: () {},
                child: const Text('Sign in'),
              ),
            ),
            const Text('Home'),
          ],
        ),
      ),
    );

Widget _hostWithClerk(ClerkAuthState authState) => MaterialApp(
  theme: AppTheme.light(),
  home: Scaffold(
    body: ClerkAuth(
      authState: authState,
      child: ClerkErrorListener(
        handler: (context, error) => presentAuthFailure(context, error),
        child: Builder(
          builder: (context) => Column(
            children: <Widget>[
              TextButton(
                key: const ValueKey<String>('host.action'),
                onPressed: () {},
                child: const Text('Sign in'),
              ),
              const Text('Home'),
            ],
          ),
        ),
      ),
    ),
  ),
);

Future<void> _report(
  WidgetTester tester, {
  AppDiagnostics diagnostics = const NoopAppDiagnostics(),
  clerk.ClerkError? failure,
}) async {
  final context = tester.element(
    find.byKey(const ValueKey<String>('host.action')),
  );
  presentAuthFailure(context, failure ?? _failure, diagnostics: diagnostics);
  await tester.pump();
  await tester.pump();
}

String _snackBarText(WidgetTester tester) {
  final snackBar = tester.widget<SnackBar>(find.byType(SnackBar));
  return (snackBar.content as Text).data!;
}

/// Builds a sign-in awaiting [strategy].
///
/// [SignIn.verification] reads `second_factor_verification` once the sign-in
/// has moved past its first factor, so that entry is set alongside the first.
clerk.SignIn _signIn({
  required clerk.Status status,
  clerk.Strategy strategy = clerk.Strategy.password,
}) {
  Map<String, Object?> verification() => <String, Object?>{
    'status': clerk.Status.unverified.name,
    'strategy': strategy.name,
  };

  return clerk.SignIn.fromJson(<String, Object?>{
    'id': 'sia_01h8x9j2m4k5n6p7q8r9s0t1',
    'status': status.name,
    'identifier': 'person@example.com',
    'first_factor_verification': verification(),
    'second_factor_verification': verification(),
  });
}

clerk.Client _clientWithSignIn(clerk.SignIn signIn) =>
    clerk.Client.fromJson(<String, Object?>{
      'id': 'client_01h8x9j2m4k5n6p7q8r9s0t1',
      'sign_in': signIn.toJson(),
      'sessions': <Object?>[],
    });

clerk.Client _clientWithSession() => clerk.Client.fromJson(<String, Object?>{
  'id': 'client_01h8x9j2m4k5n6p7q8r9s0t1',
  'sessions': <Object?>[],
});

clerk.Client _clientWithSignUp(clerk.SignUp signUp) =>
    clerk.Client.fromJson(<String, Object?>{
      'id': 'client_01h8x9j2m4k5n6p7q8r9s0t1',
      'sign_up': signUp.toJson(),
      'sessions': <Object?>[],
    });

/// Builds a sign-up whose email verification is either done or outstanding.
Map<String, Object?> _signUpJson({required bool verified}) =>
    <String, Object?>{
      'id': 'su_01h8x9j2m4k5n6p7q8r9s0t1',
      'status': clerk.Status.missingRequirements.name,
      'email_address': 'person@example.com',
      'required_fields': <Object?>[],
      'optional_fields': <Object?>[],
      'missing_fields': <Object?>[],
      'unverified_fields': verified ? <Object?>[] : <Object?>['email_address'],
      'password_enabled': true,
      'unsafe_metadata': <String, Object?>{},
      'public_metadata': <String, Object?>{},
      'custom_action': false,
      'verifications': <String, Object?>{
        'email_address': <String, Object?>{
          'status': (verified ? clerk.Status.verified : clerk.Status.unverified).name,
          'strategy': clerk.Strategy.emailCode.name,
        },
      },
    };

/// Builds a [ClerkAuthState] without contacting Clerk.
///
/// Clerk fetches its environment on a one-second timeout and falls back to
/// empty, so this resolves locally against the in-memory persistor. Clerk's
/// default file cache asks for a documents directory, which the test binding
/// has no platform for, so a temporary directory is supplied instead.
Future<ClerkAuthState> _createAuthState() async {
  final directory = await Directory.systemTemp.createTemp('clerk-auth-test');
  addTearDown(() => directory.delete(recursive: true));
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  const channel = MethodChannel('plugins.flutter.io/path_provider');
  messenger.setMockMethodCallHandler(
    channel,
    (call) async => directory.path,
  );
  addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
  return ClerkAuthState.create(
    config: ClerkAuthConfig(
      publishableKey: 'pk_test_Y2xlcmsuaW50ZXN0LWtleQ',
      sessionTokenPolling: false,
    ),
  );
}