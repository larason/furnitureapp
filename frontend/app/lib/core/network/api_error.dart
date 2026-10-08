class ApiErrorItem {
  const ApiErrorItem({
    required this.code,
    required this.message,
    this.field,
    this.details,
  });

  final String code;
  final String message;
  final String? field;
  final Map<String, Object?>? details;
}

class ApiError implements Exception {
  const ApiError({
    required this.statusCode,
    required this.errors,
    this.requestId,
    this.retryAfterSeconds,
    this.invalidResponse = false,
  });

  final int statusCode;
  final List<ApiErrorItem> errors;
  final String? requestId;
  final int? retryAfterSeconds;
  final bool invalidResponse;

  @override
  String toString() => invalidResponse
      ? 'ApiError: invalid API response ($statusCode)'
      : 'ApiError: API request failed ($statusCode)';
}
