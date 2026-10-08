import '../network/api_error.dart';
import '../network/api_transport_exception.dart';

enum ErrorRecoveryAction { none, retry, refresh }

class ErrorPresentation {
  const ErrorPresentation({
    required this.title,
    required this.message,
    this.recoveryAction = ErrorRecoveryAction.none,
    this.requestId,
    this.retryAfterSeconds,
    this.fieldErrors = const <String, List<String>>{},
  });

  final String title;
  final String message;
  final ErrorRecoveryAction recoveryAction;
  final String? requestId;
  final int? retryAfterSeconds;
  final Map<String, List<String>> fieldErrors;
}

abstract final class ErrorPresentationMapper {
  static ErrorPresentation? from(Object error) {
    if (error is ApiTransportException &&
        error.kind == ApiTransportFailureKind.cancellation) {
      return null;
    }
    if (error is ApiTransportException) return _fromTransport(error);
    if (error is ApiError) return _fromApi(error);
    return const ErrorPresentation(
      title: 'Something went wrong',
      message: 'We could not complete that request. Please try again later.',
    );
  }

  static ErrorPresentation _fromTransport(ApiTransportException error) {
    return switch (error.kind) {
      ApiTransportFailureKind.connection => ErrorPresentation(
        title: 'Connection problem',
        message: 'Check your connection and try again.',
        recoveryAction: ErrorRecoveryAction.retry,
        requestId: error.requestId,
      ),
      ApiTransportFailureKind.timeout => ErrorPresentation(
        title: 'Request timed out',
        message: 'The request did not complete. Please try again.',
        recoveryAction: ErrorRecoveryAction.retry,
        requestId: error.requestId,
      ),
      ApiTransportFailureKind.invalidResponse ||
      ApiTransportFailureKind.invalidEnvelope ||
      ApiTransportFailureKind.unsupportedContentType => ErrorPresentation(
        title: 'Unexpected response',
        message: 'The service returned an unexpected response.',
        recoveryAction: ErrorRecoveryAction.retry,
        requestId: error.requestId,
      ),
      ApiTransportFailureKind.cancellation => throw StateError(
        'Cancellation must be handled before transport mapping.',
      ),
    };
  }

  static ErrorPresentation _fromApi(ApiError error) {
    final fields = _fieldErrors(error.errors);
    final firstMessage = _firstMessage(error.errors);
    if (error.invalidResponse) {
      return ErrorPresentation(
        title: 'Unexpected response',
        message: 'The service returned an unexpected response.',
        recoveryAction: ErrorRecoveryAction.retry,
        requestId: error.requestId,
        fieldErrors: fields,
      );
    }
    return switch (error.statusCode) {
      401 => ErrorPresentation(
        title: 'Authentication required',
        message: 'Please sign in to continue.',
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      403 => ErrorPresentation(
        title: 'Access denied',
        message: 'You do not have permission to perform this action.',
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      404 => ErrorPresentation(
        title: 'Not found',
        message: 'The requested resource is no longer available.',
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      409 => ErrorPresentation(
        title: 'State changed',
        message: 'The information changed. Refresh and try again.',
        recoveryAction: ErrorRecoveryAction.refresh,
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      422 => ErrorPresentation(
        title: 'Check your information',
        message: firstMessage ?? 'Some information needs attention.',
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      429 => ErrorPresentation(
        title: 'Too many requests',
        message: error.retryAfterSeconds == null
            ? 'Please wait and try again later.'
            : 'Please wait before trying again.',
        requestId: error.requestId,
        retryAfterSeconds: error.retryAfterSeconds,
        fieldErrors: fields,
      ),
      >= 500 => ErrorPresentation(
        title: 'Service temporarily unavailable',
        message: 'Please try again later.',
        recoveryAction: ErrorRecoveryAction.retry,
        requestId: error.requestId,
        fieldErrors: fields,
      ),
      _ => ErrorPresentation(
        title: 'Request could not be completed',
        message: 'Please try again later.',
        requestId: error.requestId,
        fieldErrors: fields,
      ),
    };
  }

  static Map<String, List<String>> _fieldErrors(List<ApiErrorItem> errors) {
    final fields = <String, List<String>>{};
    for (final error in errors) {
      final field = error.field;
      if (field == null || field.isEmpty) continue;
      fields.putIfAbsent(field, () => <String>[]).add(error.message);
    }
    return fields;
  }

  static String? _firstMessage(List<ApiErrorItem> errors) {
    for (final error in errors) {
      if (error.field == null && error.message.isNotEmpty) return error.message;
    }
    return null;
  }
}
