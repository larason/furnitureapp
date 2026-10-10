import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import '../../config/app_config.dart';
import 'api_error.dart';
import 'api_response.dart';
import 'api_transport.dart';
import 'api_transport_exception.dart';
import 'auth_token_provider.dart';
import '../diagnostics/app_diagnostics.dart';
import '../diagnostics/diagnostic_category.dart';
import '../diagnostics/diagnostic_code.dart';
import '../diagnostics/diagnostic_event.dart';
import '../diagnostics/diagnostic_level.dart';
import '../diagnostics/diagnostic_sanitizer.dart';
import 'network_constants.dart';
import 'request_cancellation.dart';

typedef ApiDecoder<T> = T Function(Object? value);

class ApiConfigurationException implements Exception {
  const ApiConfigurationException(this.message);

  final String message;

  @override
  String toString() => 'ApiConfigurationException: $message';
}

class ApiClient {
  ApiClient({
    required AppConfig config,
    ApiTransport? transport,
    this.authTokenProvider,
    this.diagnostics = const NoopAppDiagnostics(),
    Duration timeout = const Duration(
      milliseconds: NetworkConstants.defaultTimeoutMs,
    ),
  }) : _origin = _validateOrigin(config.apiBaseUrl),
       _transport = transport ?? HttpApiTransport(),
       _ownsTransport = transport == null,
       _defaultTimeout = _validateTimeout(timeout);

  final Uri _origin;
  final ApiTransport _transport;
  final bool _ownsTransport;
  final AuthTokenProvider? authTokenProvider;
  final Duration _defaultTimeout;
  final AppDiagnostics diagnostics;
  bool _isClosed = false;

  Future<ApiResponse<T>?> get<T>(
    String path, {
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) => request<T>(
    path,
    method: ApiHttpMethod.get,
    queryParameters: queryParameters,
    authMode: authMode,
    headers: headers,
    cancellation: cancellation,
    timeout: timeout,
    decoder: decoder,
  );

  Future<ApiResponse<T>?> post<T>(
    String path, {
    Object? body,
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) => request<T>(
    path,
    method: ApiHttpMethod.post,
    body: body,
    queryParameters: queryParameters,
    authMode: authMode,
    headers: headers,
    cancellation: cancellation,
    timeout: timeout,
    decoder: decoder,
  );

  Future<ApiResponse<T>?> patch<T>(
    String path, {
    Object? body,
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) => request<T>(
    path,
    method: ApiHttpMethod.patch,
    body: body,
    queryParameters: queryParameters,
    authMode: authMode,
    headers: headers,
    cancellation: cancellation,
    timeout: timeout,
    decoder: decoder,
  );

  Future<ApiResponse<T>?> put<T>(
    String path, {
    Object? body,
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) => request<T>(
    path,
    method: ApiHttpMethod.put,
    body: body,
    queryParameters: queryParameters,
    authMode: authMode,
    headers: headers,
    cancellation: cancellation,
    timeout: timeout,
    decoder: decoder,
  );

  Future<ApiResponse<T>?> delete<T>(
    String path, {
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) => request<T>(
    path,
    method: ApiHttpMethod.delete,
    queryParameters: queryParameters,
    authMode: authMode,
    headers: headers,
    cancellation: cancellation,
    timeout: timeout,
    decoder: decoder,
  );

  Future<ApiResponse<T>?> request<T>(
    String path, {
    ApiHttpMethod method = ApiHttpMethod.get,
    Object? body,
    ApiRequestBody? requestBody,
    Map<String, Object?> queryParameters = const {},
    ApiAuthMode authMode = ApiAuthMode.public,
    Map<String, String> headers = const {},
    RequestCancellation? cancellation,
    Duration? timeout,
    ApiDecoder<T>? decoder,
  }) async {
    _ensureOpen();
    if (body != null && requestBody != null) {
      throw const ApiConfigurationException(
        'Provide either a JSON body or a request body, not both.',
      );
    }
    if (requestBody != null && body == null) {
      _validateRequestBodyMethod(method);
    }
    if (cancellation?.isCancelled ?? false) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }

    final requestTimeout = _validateTimeout(timeout ?? _defaultTimeout);
    final uri = _buildUri(path, queryParameters);
    final encodedBody = requestBody ?? _encodeJsonBody(body, method);
    final abort = Completer<void>();
    var timedOut = false;
    var cancelled = false;
    final timer = Timer(requestTimeout, () {
      timedOut = true;
      if (!abort.isCompleted) abort.complete();
    });
    void cancelRequest() {
      cancelled = true;
      if (!abort.isCompleted) abort.complete();
    }

    cancellation?.future.then((_) => cancelRequest());
    try {
      final token = await _resolveToken(authMode);
      final requestHeaders = _buildHeaders(
        headers,
        encodedBody,
        authMode,
        token,
      );
      if (timedOut || cancelled) {
        throw _cancellationFailure(timedOut);
      }
      final response = await _transport.send(
        ApiTransportRequest(
          method: method,
          uri: uri,
          headers: requestHeaders,
          body: encodedBody,
          abortTrigger: abort.future,
        ),
      );
      if (timedOut || cancelled) {
        throw _cancellationFailure(timedOut);
      }
      return await _decodeResponse<T>(response, decoder);
    } on ApiError catch (error) {
      _recordApiError(method, error);
      rethrow;
    } on ApiTransportException catch (error) {
      _recordTransportFailure(method, error);
      rethrow;
    } on ApiAuthenticationException {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.info,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.authSessionRenewalFailed,
          context: DiagnosticContext(
            operation: DiagnosticOperation.authSessionRenewal,
            transportFailure: DiagnosticTransportFailure.authentication,
          ),
        ),
      );
      rethrow;
    } on ApiConfigurationException {
      rethrow;
    } on TimeoutException {
      final failure = _cancellationFailure(true);
      _recordTransportFailure(method, failure);
      throw failure;
    } catch (_) {
      final failure = timedOut || cancelled
          ? _cancellationFailure(timedOut)
          : const ApiTransportException(
              kind: ApiTransportFailureKind.connection,
            );
      _recordTransportFailure(method, failure);
      throw failure;
    } finally {
      timer.cancel();
    }
  }

  /// Records a transport failure unless it is an expected cancellation.
  ///
  /// A transport may already fail with an [ApiTransportException], or it may
  /// surface a raw `dart:io`/`package:http` error that this client converts
  /// into one. Both routes converge here, so every eligible failure is recorded
  /// exactly once and an intentional cancellation stays silent.
  void _recordTransportFailure(
    ApiHttpMethod method,
    ApiTransportException error,
  ) {
    if (error.kind == ApiTransportFailureKind.cancellation) return;
    _recordTransportError(method, error);
  }

  void _recordApiError(ApiHttpMethod method, ApiError error) {
    final code = error.invalidResponse
        ? DiagnosticCode.apiInvalidResponse
        : error.statusCode == 429
        ? DiagnosticCode.apiRateLimited
        : DiagnosticCode.apiRequestFailed;
    final level = error.invalidResponse || error.statusCode >= 500
        ? DiagnosticLevel.error
        : error.statusCode == 429
        ? DiagnosticLevel.warning
        : DiagnosticLevel.info;
    diagnostics.record(
      DiagnosticEvent.now(
        level: level,
        category: DiagnosticCategory.network,
        code: code,
        requestId: DiagnosticSanitizer.requestId(error.requestId),
        context: DiagnosticContext(
          operation: DiagnosticOperation.apiRequest,
          httpMethod: _diagnosticMethod(method),
          statusCode: error.statusCode,
          apiErrorCode: DiagnosticSanitizer.apiErrorCode(
            error.errors.isEmpty ? null : error.errors.first.code,
          ),
          retryAfterSeconds: error.retryAfterSeconds,
        ),
      ),
    );
  }

  void _recordTransportError(
    ApiHttpMethod method,
    ApiTransportException error,
  ) {
    final code = switch (error.kind) {
      ApiTransportFailureKind.timeout => DiagnosticCode.apiRequestTimeout,
      ApiTransportFailureKind.connection => DiagnosticCode.apiConnectionFailed,
      ApiTransportFailureKind.invalidResponse ||
      ApiTransportFailureKind.invalidEnvelope ||
      ApiTransportFailureKind.unsupportedContentType =>
        DiagnosticCode.apiInvalidResponse,
      ApiTransportFailureKind.cancellation => DiagnosticCode.apiRequestFailed,
    };
    final level = error.kind == ApiTransportFailureKind.timeout
        ? DiagnosticLevel.warning
        : DiagnosticLevel.error;
    diagnostics.record(
      DiagnosticEvent.now(
        level: level,
        category: DiagnosticCategory.network,
        code: code,
        requestId: DiagnosticSanitizer.requestId(error.requestId),
        context: DiagnosticContext(
          operation: DiagnosticOperation.apiRequest,
          httpMethod: _diagnosticMethod(method),
          statusCode: error.statusCode,
          transportFailure: switch (error.kind) {
            ApiTransportFailureKind.connection =>
              DiagnosticTransportFailure.connection,
            ApiTransportFailureKind.timeout =>
              DiagnosticTransportFailure.timeout,
            ApiTransportFailureKind.invalidResponse =>
              DiagnosticTransportFailure.invalidResponse,
            ApiTransportFailureKind.invalidEnvelope =>
              DiagnosticTransportFailure.invalidEnvelope,
            ApiTransportFailureKind.unsupportedContentType =>
              DiagnosticTransportFailure.unsupportedContentType,
            ApiTransportFailureKind.cancellation => null,
          },
        ),
      ),
    );
  }

  DiagnosticHttpMethod _diagnosticMethod(ApiHttpMethod method) =>
      switch (method) {
        ApiHttpMethod.get => DiagnosticHttpMethod.get,
        ApiHttpMethod.post => DiagnosticHttpMethod.post,
        ApiHttpMethod.put => DiagnosticHttpMethod.put,
        ApiHttpMethod.patch => DiagnosticHttpMethod.patch,
        ApiHttpMethod.delete => DiagnosticHttpMethod.delete,
      };

  Future<String?> _resolveToken(ApiAuthMode authMode) async {
    if (authMode == ApiAuthMode.public) return null;
    final provider = authTokenProvider;
    if (provider == null) throw const ApiAuthenticationException();
    try {
      final token = await provider.getToken();
      if (token == null || token.trim().isEmpty) {
        throw const ApiAuthenticationException();
      }
      return token.trim();
    } on ApiAuthenticationException {
      rethrow;
    } catch (_) {
      throw const ApiAuthenticationException();
    }
  }

  Map<String, String> _buildHeaders(
    Map<String, String> custom,
    ApiRequestBody? body,
    ApiAuthMode authMode,
    String? token,
  ) {
    final result = <String, String>{
      NetworkConstants.acceptHeader: NetworkConstants.jsonContentType,
    };
    for (final entry in custom.entries) {
      final key = entry.key.toLowerCase();
      if (!_allowedCustomHeaders.contains(key)) {
        throw ApiConfigurationException(
          'Header "${entry.key}" is controlled by the API client.',
        );
      }
      if (entry.value.contains('\r') || entry.value.contains('\n')) {
        throw const ApiConfigurationException('Header values are invalid.');
      }
      result[entry.key] = entry.value;
    }
    if (body is ApiJsonBody) {
      result[NetworkConstants.contentTypeHeader] =
          NetworkConstants.jsonContentType;
    }
    if (authMode == ApiAuthMode.required) {
      result[NetworkConstants.authorizationHeader] = 'Bearer $token';
    }
    return result;
  }

  ApiRequestBody? _encodeJsonBody(Object? body, ApiHttpMethod method) {
    if (body == null) return null;
    _validateRequestBodyMethod(method);
    _validateJsonValue(body);
    try {
      return ApiJsonBody(Uint8List.fromList(utf8.encode(jsonEncode(body))));
    } on FormatException {
      throw const ApiConfigurationException('Request body is not valid JSON.');
    }
  }

  void _validateJsonValue(Object? value) {
    if (value == null || value is String || value is bool) return;
    if (value is num) {
      if (!value.isFinite) {
        throw const ApiConfigurationException(
          'Request body contains a non-finite number.',
        );
      }
      return;
    }
    if (value is List) {
      for (final item in value) {
        _validateJsonValue(item);
      }
      return;
    }
    if (value is Map) {
      for (final entry in value.entries) {
        if (entry.key is! String) {
          throw const ApiConfigurationException(
            'Request body keys must be strings.',
          );
        }
        _validateJsonValue(entry.value);
      }
      return;
    }
    throw const ApiConfigurationException(
      'Request body contains an unsupported value.',
    );
  }

  void _validateRequestBodyMethod(ApiHttpMethod method) {
    if (method == ApiHttpMethod.get || method == ApiHttpMethod.delete) {
      throw const ApiConfigurationException(
        'GET and DELETE requests cannot include a body.',
      );
    }
  }

  Uri _buildUri(String path, Map<String, Object?> queryParameters) {
    final normalized = _normalizePath(path);
    final uri = Uri.parse(
      '${_origin.toString()}${NetworkConstants.apiPrefix}/$normalized',
    );
    return uri.replace(query: _encodeQuery(queryParameters));
  }

  String _normalizePath(String path) {
    if (path.isEmpty ||
        path.contains('?') ||
        path.contains('#') ||
        path.contains('\\')) {
      throw const ApiConfigurationException(
        'API resource path must be relative and must not contain a query or fragment.',
      );
    }
    if (RegExp(
      r'^(?:[a-z][a-z\d+.-]*:|//)',
      caseSensitive: false,
    ).hasMatch(path)) {
      throw const ApiConfigurationException(
        'API resource path must be relative.',
      );
    }
    final normalized = path.startsWith('/') ? path.substring(1) : path;
    final segments = normalized.split('/');
    if (segments.any((segment) => segment.isEmpty || _isDotSegment(segment))) {
      throw const ApiConfigurationException(
        'API resource path contains an invalid segment.',
      );
    }
    if (segments.length >= 2 && segments[0] == 'api' && segments[1] == 'v1') {
      throw const ApiConfigurationException(
        'API resource paths must omit the /api/v1 prefix.',
      );
    }
    return segments.join('/');
  }

  bool _isDotSegment(String segment) {
    try {
      final decoded = Uri.decodeComponent(segment);
      return decoded == '.' || decoded == '..';
    } on FormatException {
      throw const ApiConfigurationException(
        'API resource path has invalid encoding.',
      );
    }
  }

  String _encodeQuery(Map<String, Object?> query) {
    final encoded = <String>[];
    for (final entry in query.entries) {
      final values = entry.value is List
          ? (entry.value as List<Object?>)
          : <Object?>[entry.value];
      for (final value in values) {
        if (value == null) continue;
        if (value is num && !value.isFinite) {
          throw ApiConfigurationException(
            'Query parameter "${entry.key}" must be finite.',
          );
        }
        if (value is! String && value is! num && value is! bool) {
          throw ApiConfigurationException(
            'Query parameter "${entry.key}" has an unsupported value.',
          );
        }
        encoded.add(
          '${Uri.encodeQueryComponent(entry.key)}='
          '${Uri.encodeQueryComponent(value.toString())}',
        );
      }
    }
    return encoded.join('&');
  }

  Future<ApiResponse<T>?> _decodeResponse<T>(
    ApiTransportResponse response,
    ApiDecoder<T>? decoder,
  ) async {
    final responseId = DiagnosticSanitizer.requestId(
      _header(response.headers, NetworkConstants.requestIdHeader),
    );
    final retryAfter = _parseRetryAfter(
      _header(response.headers, NetworkConstants.retryAfterHeader),
    );
    if (response.statusCode == 204) return null;
    if (response.bodyBytes.isEmpty) {
      throw _invalidResponse(response.statusCode, responseId);
    }
    _validateContentType(response.headers, response.statusCode, responseId);
    final payload = _decodeJson(
      response.bodyBytes,
      response.statusCode,
      responseId,
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw _parseApiError(
        response.statusCode,
        payload,
        responseId,
        retryAfter,
      );
    }
    if (payload is! Map || !payload.containsKey('data')) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.invalidEnvelope,
      );
    }
    final meta = _parseMeta(payload['meta'], response.statusCode, responseId);
    late final T data;
    if (decoder == null) {
      data = _castData<T>(payload['data'], response.statusCode, responseId);
    } else {
      try {
        data = decoder(payload['data']);
      } catch (_) {
        throw _invalidResponse(response.statusCode, responseId);
      }
    }
    return ApiResponse(data: data, meta: meta);
  }

  T _castData<T>(Object? value, int statusCode, String? requestId) {
    if (value is T) return value;
    throw _invalidResponse(statusCode, requestId);
  }

  Object? _decodeJson(Uint8List bytes, int statusCode, String? requestId) {
    try {
      return jsonDecode(utf8.decode(bytes));
    } on FormatException {
      throw _invalidResponse(statusCode, requestId);
    }
  }

  ApiError _parseApiError(
    int statusCode,
    Object? payload,
    String? responseId,
    int? retryAfter,
  ) {
    if (payload is! Map ||
        payload['errors'] is! List ||
        payload['meta'] is! Map) {
      return _invalidResponse(statusCode, responseId, retryAfter);
    }
    final meta = payload['meta'] as Map;
    final bodyRequestId = meta['request_id'];
    if (bodyRequestId is! String ||
        DiagnosticSanitizer.requestId(bodyRequestId) == null) {
      return _invalidResponse(statusCode, responseId, retryAfter);
    }
    final errors = <ApiErrorItem>[];
    for (final item in payload['errors'] as List) {
      if (item is! Map ||
          item['code'] is! String ||
          item['message'] is! String) {
        return _invalidResponse(statusCode, responseId, retryAfter);
      }
      final field = item['field'];
      final details = item['details'];
      if (field != null && field is! String ||
          details != null && details is! Map) {
        return _invalidResponse(statusCode, responseId, retryAfter);
      }
      errors.add(
        ApiErrorItem(
          code: item['code'] as String,
          message: item['message'] as String,
          field: field as String?,
          details: details == null
              ? null
              : _stringKeyedMap(details as Map<Object?, Object?>),
        ),
      );
    }
    if (errors.isEmpty) {
      return _invalidResponse(statusCode, responseId, retryAfter);
    }
    return ApiError(
      statusCode: statusCode,
      errors: errors,
      requestId: bodyRequestId,
      retryAfterSeconds: retryAfter,
    );
  }

  ApiMeta? _parseMeta(Object? value, int statusCode, String? responseId) {
    if (value == null) {
      return responseId == null ? null : ApiMeta(requestId: responseId);
    }
    if (value is! Map) throw _invalidResponse(statusCode, responseId);
    final paginationValue = value['pagination'];
    final unreadValue = value['unread_count'];
    final requestValue = value['request_id'];
    if (requestValue != null &&
        (requestValue is! String ||
            DiagnosticSanitizer.requestId(requestValue) == null)) {
      throw _invalidResponse(statusCode, responseId);
    }
    if (unreadValue != null && (unreadValue is! int || unreadValue < 0)) {
      throw _invalidResponse(statusCode, responseId);
    }
    return ApiMeta(
      pagination: paginationValue == null
          ? null
          : _parsePagination(paginationValue as Object, statusCode, responseId),
      unreadCount: unreadValue as int?,
      requestId: requestValue as String? ?? responseId,
    );
  }

  ApiPagination _parsePagination(
    Object value,
    int statusCode,
    String? responseId,
  ) {
    if (value is! Map) throw _invalidResponse(statusCode, responseId);
    final current = value['current_page'];
    final perPage = value['per_page'];
    final total = value['total'];
    final last = value['last_page'];
    final next = value['has_next'];
    final previous = value['has_previous'];
    if (current is! int ||
        current < 1 ||
        perPage is! int ||
        perPage < 1 ||
        perPage > 100 ||
        total is! int ||
        total < 0 ||
        last is! int ||
        last < 1 ||
        next is! bool ||
        previous is! bool) {
      throw _invalidResponse(statusCode, responseId);
    }
    return ApiPagination(
      currentPage: current,
      perPage: perPage,
      total: total,
      lastPage: last,
      hasNext: next,
      hasPrevious: previous,
    );
  }

  void _validateContentType(
    Map<String, String> headers,
    int statusCode,
    String? requestId,
  ) {
    final contentType = _header(headers, NetworkConstants.contentTypeHeader);
    if (contentType != null &&
        !contentType.toLowerCase().startsWith(
          NetworkConstants.jsonContentType,
        )) {
      throw ApiTransportException(
        kind: ApiTransportFailureKind.unsupportedContentType,
        statusCode: statusCode,
        requestId: requestId,
      );
    }
  }

  ApiError _invalidResponse(
    int statusCode,
    String? requestId, [
    int? retryAfter,
  ]) {
    return ApiError(
      statusCode: statusCode,
      errors: const [],
      requestId: requestId,
      retryAfterSeconds: retryAfter,
      invalidResponse: true,
    );
  }

  ApiTransportException _cancellationFailure(bool timedOut) =>
      ApiTransportException(
        kind: timedOut
            ? ApiTransportFailureKind.timeout
            : ApiTransportFailureKind.cancellation,
      );

  static Uri _validateOrigin(String value) {
    final uri = Uri.tryParse(value);
    if (uri == null ||
        uri.host.isEmpty ||
        (uri.scheme != 'http' && uri.scheme != 'https') ||
        uri.userInfo.isNotEmpty ||
        uri.query.isNotEmpty ||
        uri.fragment.isNotEmpty ||
        (uri.path.isNotEmpty && uri.path != '/')) {
      throw const ApiConfigurationException(
        'API origin must be an HTTP(S) origin.',
      );
    }
    return uri.replace(path: '');
  }

  static Duration _validateTimeout(Duration value) {
    final milliseconds = value.inMilliseconds;
    if (milliseconds < 1 ||
        milliseconds > NetworkConstants.maxTimeoutMs ||
        value.inMicroseconds !=
            milliseconds * Duration.microsecondsPerMillisecond) {
      throw const ApiConfigurationException(
        'Timeout must be a positive millisecond duration.',
      );
    }
    return value;
  }

  void _ensureOpen() {
    if (_isClosed) {
      throw const ApiConfigurationException('API client is closed.');
    }
  }

  Future<void> close() async {
    if (_isClosed) return;
    _isClosed = true;
    if (_ownsTransport) await _transport.close();
  }

  static String? _header(Map<String, String> headers, String name) {
    final wanted = name.toLowerCase();
    for (final entry in headers.entries) {
      if (entry.key.toLowerCase() == wanted) return entry.value;
    }
    return null;
  }

  static int? _parseRetryAfter(String? value) {
    if (value == null || !RegExp(r'^\d+$').hasMatch(value.trim())) return null;
    final seconds = int.tryParse(value.trim());
    return seconds == null || seconds < 0 ? null : seconds;
  }

  static Map<String, Object?> _stringKeyedMap(Map<Object?, Object?> value) {
    final result = <String, Object?>{};
    for (final entry in value.entries) {
      if (entry.key is String) result[entry.key as String] = entry.value;
    }
    return result;
  }

  static const Set<String> _allowedCustomHeaders = <String>{
    'idempotency-key',
    'x-guest-cart-id',
    'x-upload-token',
  };
}
