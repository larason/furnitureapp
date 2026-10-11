import 'package:clerk_auth/clerk_auth.dart';

/// The authentication flow an error happened in.
///
/// Clerk reports one flat stream of failures with no flow attached, but the
/// right message depends entirely on where the customer was: a rejected
/// verification code needs different guidance from a rejected sign-in.
enum AuthFailureFlow {
  signIn,
  signUp,
  verification,
  recovery,
  general,
}

/// Customer-facing copy for a [ClerkError] raised in [flow].
///
/// **This is defense in depth, not enumeration protection.** Clerk decides
/// whether an identifier exists and what it returns; this function only
/// normalises what the app chooses to display. It cannot hide existence from a
/// determined caller, because the underlying status code, response timing and
/// Clerk's own copy still distinguish it. The real control is Clerk's
/// enumeration protection in the Dashboard (bulk or strict), which is the only
/// thing that changes what the server discloses.
///
/// Clerk's own text carries server diagnostics and internal identifiers, so
/// neither the message nor the argument is ever rendered. Only recognised codes
/// reach the screen; everything else falls back to one safe message for the
/// flow.
String authFailureMessage(ClerkError error, AuthFailureFlow flow) =>
    authFailureMessageFor(
      serverCodes: serverErrorCodes(error),
      sdkCode: error.code,
      flow: flow,
    );

/// Resolves copy from Clerk identifiers alone.
///
/// The message text carried by the SDK is deliberately not a parameter: it is
/// the only part of a failure that can leak internals, so this cannot be
/// reached by anything other than the [authFailureMessage] entry point above.
String authFailureMessageFor({
  Iterable<String> serverCodes = const <String>[],
  ClerkErrorCode? sdkCode,
  AuthFailureFlow flow = AuthFailureFlow.general,
}) {
  final codes = <String>[...serverCodes, if (sdkCode != null) sdkCode.name];
  for (final code in codes) {
    final message = _flowMessages[flow]?[code] ?? _sharedMessages[code];
    if (message != null) return message;
  }
  return _flowFallbacks[flow] ?? _sharedFallback;
}

const String _sharedFallback = 'Something went wrong. Please try again.';

const String _checkConnection = 'Check your connection and try again.';

const String _sessionEnded = 'Your session has ended. Please sign in again.';

const String _tooManyAttempts = 'Too many attempts. Wait a moment and try again.';

const String _codeIncorrect = 'That code is not correct.';

const String _codeExpired = 'That code has expired. Request a new one.';

/// Neutral sign-in copy for an identifier Clerk would not accept.
///
/// It deliberately does not distinguish "no such account" from "wrong
/// password", because that difference is exactly what lets an attacker
/// enumerate registered addresses.
const String _signInRejected =
    'Those sign-in details were not accepted. Check them and try again.';

/// Neutral sign-up copy for an identifier Clerk would not accept.
///
/// It deliberately does not confirm that an account exists. Clerk owns that
/// decision: see the enumeration note in the class docs for
/// [authFailureMessageFor].
const String _signUpRejected =
    'We could not create an account with those details. If you already have '
    'an account, sign in instead.';

/// Keys are [ClerkErrorCode] names and Clerk server error codes.
const Map<String, String> _sharedMessages = <String, String>{
  // Password rules and validation. These describe the input, never the
  // account, so they stay specific and useful.
  'passwordMatchError': 'Those passwords do not match.',
  'form_password_too_short': 'Choose a longer password.',
  'form_password_pwned':
      'That password has appeared in a data breach. Choose a different one.',
  'form_param_missing': 'Please complete every field.',
  'form_param_failed': 'Please check that field.',
  'form_param_format_invalid': 'Check the format of that field.',

  // Temporary and infrastructure failures. Naming the cause here is safe and
  // tells the customer whether to wait or to check their connection.
  'tooManyRetries': _tooManyAttempts,
  'too_many_requests': _tooManyAttempts,
  'network_error': _checkConnection,
  'connection_error': _checkConnection,
  'temporary_unavailable': 'Sign-in is temporarily unavailable. Try again.',
  'clientAppError': 'We could not complete that. Please try again.',

  // Session failures.
  'noSessionTokenRetrieved': _sessionEnded,
  'noSessionFoundForUser': _sessionEnded,
  'jwtPoorlyFormatted': _sessionEnded,
  'session_exists': 'You are already signed in.',
};

const Map<String, String> _signInMessages = <String, String>{
  // Existence-revealing codes are neutralised rather than removed, so the
  // customer still gets a usable next step. Every one of them resolves to the
  // same text: an attacker comparing responses learns nothing.
  'identifier_not_found': _signInRejected,
  'form_identifier_not_found': _signInRejected,
  'form_identifier_exists': _signInRejected,
  'form_email_address_exists': _signInRejected,
  'form_param_not_found': _signInRejected,
  'form_username_exists': _signInRejected,
  'form_password_incorrect': _signInRejected,
};

const Map<String, String> _signUpMessages = <String, String>{
  // Existence-revealing codes are neutralised rather than removed.
  'form_email_address_exists': _signUpRejected,
  'form_identifier_exists': _signUpRejected,
  'form_username_exists': _signUpRejected,
  'identifier_not_found': _signUpRejected,
  'form_identifier_not_found': _signUpRejected,
  'form_param_not_found': _signUpRejected,
  'legalAcceptanceRequired': 'Please accept the terms to continue.',
};

const Map<String, String> _verificationMessages = <String, String>{
  'form_code_incorrect': _codeIncorrect,
  'form_code_expired': _codeExpired,
  'verification_failed': _codeIncorrect,
  'verification_expired': _codeExpired,
  'noInitialCodeHasBeenSetUpToResend': 'Request a new code, then try again.',
  'noAssociatedCodeRetrievalMethod':
      'We could not send a code to those details.',
};

const Map<String, String> _recoveryMessages = <String, String>{
  'form_code_incorrect': _codeIncorrect,
  'form_code_expired': _codeExpired,
  'form_param_missing': 'Please complete every field.',
  'passwordResetStrategyError':
      'That password reset method is unavailable. Choose another option.',
};

/// Per-flow overrides, checked before [_sharedMessages].
const Map<AuthFailureFlow, Map<String, String>> _flowMessages =
    <AuthFailureFlow, Map<String, String>>{
      AuthFailureFlow.signIn: _signInMessages,
      AuthFailureFlow.signUp: _signUpMessages,
      AuthFailureFlow.verification: _verificationMessages,
      AuthFailureFlow.recovery: _recoveryMessages,
    };

/// Last-resort copy when a flow recognises nothing.
const Map<AuthFailureFlow, String> _flowFallbacks = <AuthFailureFlow, String>{
  AuthFailureFlow.signIn:
      'We could not sign you in with those details. Please try again.',
  AuthFailureFlow.signUp:
      'We could not create your account. Please try again.',
  AuthFailureFlow.verification: 'We could not verify that code. Please try again.',
  AuthFailureFlow.recovery:
      'We could not reset your password. Please try again.',
};

/// Clerk server error codes carried by a [ClerkError].
///
/// The codes are the only part of a failure that may be matched on; the
/// accompanying message and argument are never inspected.
Iterable<String> serverErrorCodes(ClerkError error) sync* {
  final serverErrors = error.errors?.errors ?? const [];
  for (final serverError in serverErrors) {
    if (serverError.code case final code?) yield code;
  }
}