import 'package:flutter/foundation.dart';

import '../../../core/auth/auth_session.dart';
import '../../../core/network/api_error.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/auth_token_provider.dart';
import '../../../core/network/request_cancellation.dart';
import '../data/enquiry_draft.dart';
import '../data/enquiry_repository.dart';

enum EnquirySubmissionState { editing, submitting, success, uncertain }

/// Owns the ENQ-001 submission lifecycle and the customer-visible outcome.
///
/// A timeout or connection failure during this non-idempotent POST may have
/// been accepted by Laravel, so that outcome is reported as uncertain and is
/// never retried without a deliberate second submission. A local cancellation
/// is never reported as a server rejection.
class EnquiryController extends ChangeNotifier {
  EnquiryController({
    required this.repository,
    required this.authSession,
    EnquiryProductContext? product,
  }) : _draft = EnquiryDraft(product: product);

  final EnquiryRepository repository;
  final AuthSession? authSession;
  EnquiryDraft _draft;
  Map<String, String> _errors = const {};
  EnquirySubmissionState _state = EnquirySubmissionState.editing;
  String? _message;
  SubmittedEnquiry? _submitted;
  RequestCancellation? _cancellation;
  bool _isDisposed = false;

  @override
  void dispose() {
    _isDisposed = true;
    super.dispose();
  }

  EnquiryDraft get draft => _draft;
  Map<String, String> get errors => _errors;
  EnquirySubmissionState get state => _state;
  String? get message => _message;
  SubmittedEnquiry? get submitted => _submitted;
  bool get isSubmitting => _state == EnquirySubmissionState.submitting;
  bool get isUncertain => _state == EnquirySubmissionState.uncertain;

  void update(EnquiryDraft draft) {
    _draft = draft;
    _errors = const {};
    if (_state == EnquirySubmissionState.uncertain) {
      _notify();
      return;
    }
    _message = null;
    if (_state != EnquirySubmissionState.submitting) {
      _state = EnquirySubmissionState.editing;
    }
    _notify();
  }

  /// A screen may be disposed while a submission is still in flight, so late
  /// completion never notifies a disposed controller.
  void _notify() {
    if (!_isDisposed) notifyListeners();
  }

  Future<void> submit() async {
    if (isSubmitting) return;
    final signedIn = authSession?.isSignedIn ?? false;
    final validation = validateEnquiry(_draft, authenticated: signedIn);
    if (validation.isNotEmpty) {
      _errors = validation;
      _message = 'Correct the highlighted details and try again.';
      _notify();
      return;
    }
    _state = EnquirySubmissionState.submitting;
    _message = null;
    _cancellation = RequestCancellation();
    _notify();
    try {
      final blocker = signedIn ? await _sessionBlocker(authSession) : null;
      if (blocker != null) {
        _state = EnquirySubmissionState.editing;
        _message = blocker;
        return;
      }
      _submitted = await repository.submit(
        _draft,
        authenticated: signedIn,
        cancellation: _cancellation,
      );
      _state = EnquirySubmissionState.success;
    } on ApiError catch (error) {
      _applyApiError(error);
    } on ApiTransportException catch (error) {
      _applyTransportError(error);
    } on ApiAuthenticationException {
      // The token is resolved before the request is sent, so Laravel never saw
      // this submission and a duplicate-warning would be misleading.
      _state = EnquirySubmissionState.editing;
      _message = _sessionMessage;
    } catch (_) {
      _state = EnquirySubmissionState.uncertain;
      _message = _uncertainMessage;
    } finally {
      _cancellation = null;
      _notify();
    }
  }

  void cancel() => _cancellation?.cancel();

  static const _uncertainMessage =
      'We could not confirm whether your enquiry was received. Sending it '
      'again may create a duplicate enquiry.';

  static const _sessionMessage =
      'Your signed-in session could not be confirmed. Sign in again or '
      'explicitly sign out to send as a visitor.';

  /// A signed-in session must never silently degrade into an anonymous
  /// enquiry, so an unavailable token stops the submission instead.
  Future<String?> _sessionBlocker(AuthSession? session) async {
    if (session == null) return _sessionMessage;
    return await _hasToken(session) ? null : _sessionMessage;
  }

  Future<bool> _hasToken(AuthSession session) async {
    try {
      return (await session.getToken()) != null;
    } catch (_) {
      return false;
    }
  }

  void _applyTransportError(ApiTransportException error) {
    if (error.kind == ApiTransportFailureKind.cancellation) {
      _state = EnquirySubmissionState.editing;
      return;
    }
    _state = EnquirySubmissionState.uncertain;
    _message = _uncertainMessage;
  }

  void _applyApiError(ApiError error) {
    _state = EnquirySubmissionState.editing;
    if (error.statusCode == 429) {
      _message = error.retryAfterSeconds == null
          ? 'Please wait before sending another enquiry.'
          : 'Please wait ${error.retryAfterSeconds} seconds before trying again.';
      return;
    }
    _errors = <String, String>{
      for (final item in error.errors)
        if (item.field != null) item.field!: item.message,
    };
    _message = switch (error.statusCode) {
      401 =>
        'Your signed-in session could not be confirmed. Sign in again before sending.',
      403 => 'This account cannot send an enquiry.',
      413 => 'That attachment is too large. Choose a smaller file.',
      404 || 409 => 'That product is no longer available for enquiries.',
      _ =>
        _errors.isEmpty
            ? 'We could not send your enquiry right now. Please try again later.'
            : 'Correct the highlighted details and try again.',
    };
  }
}
