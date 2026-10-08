abstract final class NetworkConstants {
  static const String apiPrefix = '/api/v1';
  static const String acceptHeader = 'Accept';
  static const String contentTypeHeader = 'Content-Type';
  static const String authorizationHeader = 'Authorization';
  static const String requestIdHeader = 'X-Request-Id';
  static const String retryAfterHeader = 'Retry-After';
  static const String jsonContentType = 'application/json';
  static const int defaultTimeoutMs = 10000;
  static const int maxTimeoutMs = 2147483647;
}
