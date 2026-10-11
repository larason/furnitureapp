import 'package:clerk_auth/clerk_auth.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/auth_failure_message.dart';

/// Resolves the copy for a Clerk server error code in a flow.
///
/// The SDK does not export its error-collection type, so the code path is
/// exercised through the same public resolver [authFailureMessage] delegates
/// to.
String _forCode(String code, AuthFailureFlow flow) => authFailureMessageFor(
  serverCodes: <String>[code],
  flow: flow,
);

/// Phrases that would confirm or deny that an account exists.
///
/// These are disclosure claims, not instructions: neutral copy may still tell
/// the customer what to try next, it just may not state what is true of their
/// identifier.
const List<String> _existencePhrases = <String>[
  'already exists',
  'does not exist',
  'no account',
  'not found',
  'unknown email',
  'unknown address',
  'already taken',
  'already registered',
  'in use',
];

void _expectNoExistenceLeak(String message) {
  for (final phrase in _existencePhrases) {
    expect(
      message.toLowerCase(),
      isNot(contains(phrase)),
      reason: '"$message" must not reveal whether an account exists.',
    );
  }
}

void main() {
  group('existence-revealing codes are neutral', () {
    const existenceCodes = <String>[
      'identifier_not_found',
      'form_identifier_not_found',
      'form_email_address_exists',
      'form_identifier_exists',
      'form_param_not_found',
      'form_username_exists',
    ];

    for (final code in existenceCodes) {
      test('$code does not reveal account existence on sign in', () {
        _expectNoExistenceLeak(_forCode(code, AuthFailureFlow.signIn));
      });

      test('$code does not reveal account existence on sign up', () {
        _expectNoExistenceLeak(_forCode(code, AuthFailureFlow.signUp));
      });
    }

    test('every existence-revealing sign-in code yields one identical message', () {
      // The strongest available property: an attacker comparing responses
      // learns nothing, because every code produces the same bytes.
      final messages = existenceCodes
          .map((code) => _forCode(code, AuthFailureFlow.signIn))
          .toSet();

      expect(messages, hasLength(1));
    });

    test('every existence-revealing sign-up code yields one identical message', () {
      final messages = existenceCodes
          .map((code) => _forCode(code, AuthFailureFlow.signUp))
          .toSet();

      expect(messages, hasLength(1));
    });

    test('sign-in and sign-up copy differ only by the action offered', () {
      // A single message across flows is not required, but each must stay
      // actionable for the flow the customer is actually in.
      expect(
        _forCode('form_identifier_not_found', AuthFailureFlow.signIn),
        contains('sign-in details'),
      );
      expect(
        _forCode('form_identifier_not_found', AuthFailureFlow.signUp),
        contains('sign in instead'),
      );
    });

    test('a rejected password is indistinguishable from an unknown email', () {
      // These two are the enumeration oracle: an attacker who can tell them
      // apart can confirm which addresses are registered.
      final unknownEmail = _forCode(
        'form_identifier_not_found',
        AuthFailureFlow.signIn,
      );
      final wrongPassword = _forCode(
        'form_password_incorrect',
        AuthFailureFlow.signIn,
      );

      expect(unknownEmail, wrongPassword);
    });

    test('a taken sign-up email is neutral and still actionable', () {
      // A taken address must not be confirmed as taken, yet still has to give
      // the customer something to do next.
      final taken = _forCode('form_email_address_exists', AuthFailureFlow.signUp);

      expect(taken, isNot(contains('exists')));
      expect(taken, contains('sign in'));
      expect(
        taken,
        'We could not create an account with those details. If you already '
        'have an account, sign in instead.',
      );
    });
  });

  group('sign-in feedback stays useful', () {
    test('a password requirement is specific, not generic', () {
      final message = _forCode('form_password_too_short', AuthFailureFlow.signIn);

      expect(message, 'Choose a longer password.');
      expect(message, isNot(contains('Something went wrong')));
    });

    test('a breached password is specific', () {
      expect(
        _forCode('form_password_pwned', AuthFailureFlow.signIn),
        contains('data breach'),
      );
    });

    test('a mismatched confirmation is specific', () {
      expect(
        authFailureMessageFor(
          sdkCode: ClerkErrorCode.passwordMatchError,
          flow: AuthFailureFlow.signUp,
        ),
        'Those passwords do not match.',
      );
    });

    test('a temporary failure tells the customer to wait', () {
      expect(
        _forCode('too_many_requests', AuthFailureFlow.signIn),
        'Too many attempts. Wait a moment and try again.',
      );
    });

    test('a connectivity failure names the cause', () {
      expect(
        _forCode('network_error', AuthFailureFlow.signIn),
        'Check your connection and try again.',
      );
    });

    test('an unfilled field is specific', () {
      expect(
        _forCode('form_param_missing', AuthFailureFlow.signUp),
        'Please complete every field.',
      );
    });
  });

  group('sign-up feedback stays useful', () {
    test('unaccepted legal acceptance is specific', () {
      expect(
        authFailureMessageFor(
          sdkCode: ClerkErrorCode.legalAcceptanceRequired,
          flow: AuthFailureFlow.signUp,
        ),
        'Please accept the terms to continue.',
      );
    });
  });

  group('verification and recovery feedback stays useful', () {
    test('an incorrect code is specific', () {
      for (final flow in <AuthFailureFlow>[
        AuthFailureFlow.verification,
        AuthFailureFlow.recovery,
      ]) {
        expect(
          _forCode('form_code_incorrect', flow),
          'That code is not correct.',
        );
      }
    });

    test('an expired code is specific', () {
      for (final flow in <AuthFailureFlow>[
        AuthFailureFlow.verification,
        AuthFailureFlow.recovery,
      ]) {
        expect(
          _forCode('form_code_expired', flow),
          'That code has expired. Request a new one.',
        );
      }
    });

    test('an unverifiable code is specific', () {
      expect(
        _forCode('verification_failed', AuthFailureFlow.verification),
        'That code is not correct.',
      );
    });

    test('resending is specific', () {
      expect(
        authFailureMessageFor(
          sdkCode: ClerkErrorCode.noInitialCodeHasBeenSetUpToResend,
          flow: AuthFailureFlow.verification,
        ),
        'Request a new code, then try again.',
      );
    });
  });

  group('session feedback stays useful', () {
    test('an ended session names the next step', () {
      for (final code in <ClerkErrorCode>[
        ClerkErrorCode.noSessionTokenRetrieved,
        ClerkErrorCode.noSessionFoundForUser,
        ClerkErrorCode.jwtPoorlyFormatted,
      ]) {
        expect(
          authFailureMessageFor(sdkCode: code, flow: AuthFailureFlow.general),
          'Your session has ended. Please sign in again.',
        );
      }
    });
  });

  group('unknown failures stay safe', () {
    test('an unrecognised code falls back without inventing a cause', () {
      for (final flow in AuthFailureFlow.values) {
        final message = _forCode('some_internal_code', flow);

        _expectNoExistenceLeak(message);
        expect(message, isNot(contains('some_internal_code')));
      }
    });

    test('an external exception detail is never rendered', () {
      const error = ClerkError(
        code: ClerkErrorCode.externalError,
        message: '{arg} (EXTERNAL ERROR)',
        argument: 'SocketException: 10.0.2.2:443 connection refused',
      );

      final message = authFailureMessage(error, AuthFailureFlow.general);

      expect(message, isNot(contains('SocketException')));
      expect(message, isNot(contains('EXTERNAL ERROR')));
    });

    test('a client app error is not echoed back', () {
      const error = ClerkError(
        code: ClerkErrorCode.clientAppError,
        message: 'clerk_http_failure',
      );

      expect(
        authFailureMessage(error, AuthFailureFlow.general),
        isNot(contains('clerk_http_failure')),
      );
    });

    test('each flow has its own fallback so none is silent', () {
      final fallbacks = AuthFailureFlow.values
          .map(
            (flow) => authFailureMessageFor(
              serverCodes: const ['unmapped'],
              flow: flow,
            ),
          )
          .toList();

      expect(fallbacks.toSet().length, AuthFailureFlow.values.length);
      for (final fallback in fallbacks) {
        expect(fallback, isNotEmpty);
        _expectNoExistenceLeak(fallback);
      }
    });
  });
}