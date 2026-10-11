import 'package:clerk_auth/clerk_auth.dart';
import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart' hide DiagnosticLevel;

import '../auth/auth_failure_message.dart';
import '../diagnostics/app_diagnostics.dart';
import '../diagnostics/diagnostic_category.dart';
import '../diagnostics/diagnostic_code.dart';
import '../diagnostics/diagnostic_event.dart';
import '../diagnostics/diagnostic_level.dart';

/// Reports a Clerk authentication failure to the customer.
///
/// Clerk routes every auth failure to its error stream and rethrows it when
/// nothing is listening, so a rejected email, password or code would otherwise
/// leave the customer on a spinner that never resolves into an explanation.
///
/// Only [authFailureMessage] is rendered: the SDK's own text carries server
/// diagnostics and internal identifiers, so it stays in the diagnostic record.
void presentAuthFailure(
  BuildContext context,
  ClerkError error, {
  AppDiagnostics diagnostics = const NoopAppDiagnostics(),
}) {
  diagnostics.record(
    DiagnosticEvent.now(
      level: DiagnosticLevel.warning,
      category: DiagnosticCategory.authentication,
      code: DiagnosticCode.authFlowFailed,
      context: const DiagnosticContext(operation: DiagnosticOperation.authFlow),
    ),
  );
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  final colors = Theme.of(context).colorScheme;
  messenger
    ..clearSnackBars()
    ..showSnackBar(
      SnackBar(
        backgroundColor: colors.errorContainer,
        content: Text(
          authFailureMessage(error, authFailureFlowFor(_authStateAbove(context))),
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: colors.onErrorContainer),
        ),
      ),
    );
}

/// Infers which flow is in progress, so the failure can be phrased for it.
///
/// Clerk attaches no flow to a failure, so the active resource is the only
/// signal available. Resolution uses a non-subscribing lookup: reading it must
/// not subscribe this callback to Clerk's own rebuilds. Returns null when no
/// Clerk provider is above this context, which is the case before the app root
/// has mounted one.
ClerkAuthState? _authStateAbove(BuildContext context) {
  try {
    return ClerkAuth.of(context, listen: false);
  } on Object {
    // ClerkAuth.of asserts rather than returning null when no provider is
    // present. Authentication can fail before one exists, and that must still
    // reach the customer as a generic message.
    return null;
  }
}

/// Chooses the flow from Clerk's in-progress state.
///
/// The factor being attempted decides this, not merely the fact that one is
/// pending: a rejected password is a sign-in failure and must be phrased as
/// one, while a rejected code is a verification failure.
///
/// Falls back to [AuthFailureFlow.general] when no flow is active, such as for
/// a session failure raised outside sign-in, sign-up or recovery.
AuthFailureFlow authFailureFlowFor(ClerkAuthState? auth) {
  final signUp = auth?.signUp;
  if (signUp != null) return _signUpFlow(signUp);
  final signIn = auth?.signIn;
  if (signIn == null) return AuthFailureFlow.general;
  final strategy = signIn.verification?.strategy;
  if (strategy == null) return AuthFailureFlow.signIn;
  if (strategy.isPasswordResetter) return AuthFailureFlow.recovery;
  if (strategy.requiresCode || signIn.needsSecondFactor) {
    return AuthFailureFlow.verification;
  }
  return AuthFailureFlow.signIn;
}

AuthFailureFlow _signUpFlow(SignUp signUp) {
  final verifying = signUp.verifications.values.any(
    (verification) => verification.status.isUnverified,
  );
  return verifying ? AuthFailureFlow.verification : AuthFailureFlow.signUp;
}