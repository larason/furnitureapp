enum ApiTransportFailureKind {
  connection,
  timeout,
  cancellation,
  invalidResponse,
  invalidEnvelope,
  unsupportedContentType,
}

class ApiTransportException implements Exception {
  const ApiTransportException({
    required this.kind,
    this.statusCode,
    this.requestId,
  });

  final ApiTransportFailureKind kind;
  final int? statusCode;
  final String? requestId;

  @override
  String toString() => 'ApiTransportException: ${_message(kind)}';

  static String _message(ApiTransportFailureKind kind) {
    switch (kind) {
      case ApiTransportFailureKind.connection:
        return 'the API could not be reached';
      case ApiTransportFailureKind.timeout:
        return 'the API request timed out';
      case ApiTransportFailureKind.cancellation:
        return 'the API request was cancelled';
      case ApiTransportFailureKind.invalidResponse:
        return 'the API returned an invalid response';
      case ApiTransportFailureKind.invalidEnvelope:
        return 'the API returned an invalid response envelope';
      case ApiTransportFailureKind.unsupportedContentType:
        return 'the API returned an unsupported content type';
    }
  }
}
