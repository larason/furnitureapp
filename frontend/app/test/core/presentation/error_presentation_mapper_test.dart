import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/presentation/error_presentation_mapper.dart';

void main() {
  test('cancellation produces no user-facing presentation', () {
    final result = ErrorPresentationMapper.from(
      const ApiTransportException(kind: ApiTransportFailureKind.cancellation),
    );

    expect(result, isNull);
  });

  test('maps connection and timeout to safe retry descriptions', () {
    final connection = ErrorPresentationMapper.from(
      const ApiTransportException(kind: ApiTransportFailureKind.connection),
    );
    final timeout = ErrorPresentationMapper.from(
      const ApiTransportException(kind: ApiTransportFailureKind.timeout),
    );

    expect(connection!.title, 'Connection problem');
    expect(connection.recoveryAction, ErrorRecoveryAction.retry);
    expect(timeout!.title, 'Request timed out');
    expect(timeout.recoveryAction, ErrorRecoveryAction.retry);
  });

  test('maps malformed responses without exposing transport details', () {
    final result = ErrorPresentationMapper.from(
      const ApiTransportException(
        kind: ApiTransportFailureKind.invalidEnvelope,
        requestId: 'req_transport',
      ),
    );

    expect(result!.title, 'Unexpected response');
    expect(result.message, isNot(contains('invalidEnvelope')));
    expect(result.requestId, 'req_transport');
  });

  test('preserves HTTP categories and request IDs', () {
    final cases = <int, String>{
      401: 'Authentication required',
      403: 'Access denied',
      404: 'Not found',
      409: 'State changed',
      500: 'Service temporarily unavailable',
      503: 'Service temporarily unavailable',
    };

    for (final entry in cases.entries) {
      final result = ErrorPresentationMapper.from(
        ApiError(
          statusCode: entry.key,
          errors: const <ApiErrorItem>[],
          requestId: 'req_${entry.key}',
        ),
      );
      expect(result!.title, entry.value);
      expect(result.requestId, 'req_${entry.key}');
    }
  });

  test('preserves multiple canonical field errors', () {
    final result = ErrorPresentationMapper.from(
      const ApiError(
        statusCode: 422,
        errors: <ApiErrorItem>[
          ApiErrorItem(
            code: 'INVALID_VALUE',
            message: 'Enter a name.',
            field: 'name',
          ),
          ApiErrorItem(
            code: 'INVALID_VALUE',
            message: 'Use a valid email.',
            field: 'email',
          ),
          ApiErrorItem(
            code: 'INVALID_VALUE',
            message: 'Enter a name again.',
            field: 'name',
          ),
        ],
      ),
    );

    expect(result!.fieldErrors, {
      'name': ['Enter a name.', 'Enter a name again.'],
      'email': ['Use a valid email.'],
    });
    expect(result.message, 'Some information needs attention.');
  });

  test('maps rate limits without inventing a deadline', () {
    final withRetryAfter = ErrorPresentationMapper.from(
      const ApiError(
        statusCode: 429,
        errors: <ApiErrorItem>[],
        retryAfterSeconds: 30,
      ),
    );
    final withoutRetryAfter = ErrorPresentationMapper.from(
      const ApiError(statusCode: 429, errors: <ApiErrorItem>[]),
    );

    expect(withRetryAfter!.message, 'Please wait before trying again.');
    expect(withRetryAfter.recoveryAction, ErrorRecoveryAction.none);
    expect(withRetryAfter.retryAfterSeconds, 30);
    expect(withoutRetryAfter!.message, 'Please wait and try again later.');
    expect(withoutRetryAfter.retryAfterSeconds, isNull);
  });

  test('uses a generic safe fallback for unknown exceptions', () {
    final result = ErrorPresentationMapper.from(
      StateError('secret token and internal path'),
    );

    expect(result!.title, 'Something went wrong');
    expect(result.message, isNot(contains('secret')));
    expect(result.message, isNot(contains('internal')));
  });
}
