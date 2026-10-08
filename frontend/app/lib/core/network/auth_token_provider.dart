abstract interface class AuthTokenProvider {
  Future<String?> getToken();
}

enum ApiAuthMode { public, required }

class ApiAuthenticationException implements Exception {
  const ApiAuthenticationException();

  @override
  String toString() =>
      'ApiAuthenticationException: authentication is unavailable';
}
