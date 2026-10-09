import 'package:flutter/foundation.dart';

import '../../../core/auth/auth_session.dart';
import '../../../core/network/api_error.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../data/furniture_request.dart';
import '../data/furniture_request_repository.dart';

enum FurnitureRequestSubmissionState { editing, submitting, success, uncertain }

class FurnitureRequestController extends ChangeNotifier {
  FurnitureRequestController({
    required this.repository,
    required this.authSession,
    FurnitureRequestProductContext? product,
  }) : _draft = FurnitureRequestDraft(product: product);

  final FurnitureRequestRepository repository;
  final AuthSession? authSession;
  FurnitureRequestDraft _draft;
  Map<String, String> _errors = const {};
  FurnitureRequestSubmissionState _state =
      FurnitureRequestSubmissionState.editing;
  String? _message;
  SubmittedFurnitureRequest? _submitted;
  RequestCancellation? _cancellation;

  FurnitureRequestDraft get draft => _draft;
  Map<String, String> get errors => _errors;
  FurnitureRequestSubmissionState get state => _state;
  String? get message => _message;
  SubmittedFurnitureRequest? get submitted => _submitted;
  bool get isSubmitting => _state == FurnitureRequestSubmissionState.submitting;

  void update(FurnitureRequestDraft draft) {
    _draft = draft;
    _errors = const {};
    _message = null;
    if (_state != FurnitureRequestSubmissionState.submitting) {
      _state = FurnitureRequestSubmissionState.editing;
    }
    notifyListeners();
  }

  Future<void> submit() async {
    if (isSubmitting) return;
    final validation = validateFurnitureRequest(_draft);
    if (validation.isNotEmpty) {
      _errors = validation;
      _message = 'Correct the highlighted details and try again.';
      notifyListeners();
      return;
    }
    _state = FurnitureRequestSubmissionState.submitting;
    _message = null;
    _cancellation = RequestCancellation();
    notifyListeners();
    try {
      final session = authSession;
      final signedIn = session?.isSignedIn ?? false;
      if (signedIn && await session!.getToken() == null) {
        _message =
            'Your signed-in session could not be confirmed. Sign in again or explicitly sign out to submit as a visitor.';
        _state = FurnitureRequestSubmissionState.editing;
        return;
      }
      _submitted = await repository.submit(
        _draft,
        authenticated: signedIn,
        cancellation: _cancellation,
      );
      _state = FurnitureRequestSubmissionState.success;
    } on ApiError catch (error) {
      _applyApiError(error);
    } on ApiTransportException catch (error) {
      _state = error.kind == ApiTransportFailureKind.cancellation
          ? FurnitureRequestSubmissionState.editing
          : FurnitureRequestSubmissionState.uncertain;
      _message = error.kind == ApiTransportFailureKind.cancellation
          ? null
          : 'We could not confirm whether your request was received. Retrying may create another request.';
    } catch (_) {
      _state = FurnitureRequestSubmissionState.uncertain;
      _message =
          'We could not confirm whether your request was received. Retrying may create another request.';
    } finally {
      _cancellation = null;
      notifyListeners();
    }
  }

  void cancel() => _cancellation?.cancel();

  void _applyApiError(ApiError error) {
    _state = FurnitureRequestSubmissionState.editing;
    if (error.statusCode == 429) {
      _message = error.retryAfterSeconds == null
          ? 'Please wait before submitting another request.'
          : 'Please wait ${error.retryAfterSeconds} seconds before trying again.';
      return;
    }
    _errors = <String, String>{
      for (final item in error.errors)
        if (item.field != null)
          item.field!.replaceFirst('dimensions.', ''): item.message,
    };
    _message = switch (error.statusCode) {
      401 =>
        'Your signed-in session could not be confirmed. Sign in again before submitting.',
      403 => 'This account cannot submit a furniture request.',
      404 || 409 =>
        'This furniture is no longer available for requests. You can submit a custom request instead.',
      _ =>
        _errors.isEmpty
            ? 'We could not submit your request right now. Please try again later.'
            : 'Correct the highlighted details and try again.',
    };
  }
}
