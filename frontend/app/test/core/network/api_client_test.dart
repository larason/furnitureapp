import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';

void main() {
  group('URL construction', () {
    test(
      'appends the API prefix once and encodes repeated query values',
      () async {
        final transport = FakeTransport.success({'id': '1'});
        final client = _client(transport);

        await client.get<Object?>(
          '/products/chair%2Fblue',
          queryParameters: <String, Object?>{
            'search': 'oak chair',
            'tag': <String>['new', 'sale'],
            'empty': '',
            'ignored': null,
          },
        );

        expect(
          transport.lastRequest!.uri.toString(),
          'http://127.0.0.1:8000/api/v1/products/chair%2Fblue?'
          'search=oak+chair&tag=new&tag=sale&empty=',
        );
      },
    );

    test('rejects absolute, versioned, and traversal paths', () async {
      final client = _client(FakeTransport.success({'id': '1'}));

      for (final path in <String>[
        'https://evil.example/products',
        '//evil.example/products',
        '/api/v1/products',
        '/products/../users',
        '/products/%2e%2e/users',
      ]) {
        await expectLater(
          client.get<Object?>(path),
          throwsA(isA<ApiConfigurationException>()),
        );
      }
    });
  });

  group('requests and authentication', () {
    test('public GET sends standard headers without authorization', () async {
      final transport = FakeTransport.success({'id': '1'});
      final client = _client(transport);

      await client.get<Object?>('/products');

      expect(transport.lastRequest!.method, ApiHttpMethod.get);
      expect(transport.lastRequest!.headers, <String, String>{
        'Accept': 'application/json',
      });
      expect(transport.lastRequest!.body, isNull);
    });

    test(
      'POST encodes JSON and permits only contract custom headers',
      () async {
        final transport = FakeTransport.success({'created': true});
        final client = _client(transport);

        await client.post<Object?>(
          '/requests',
          body: <String, Object?>{'quantity': 2, 'notes': null},
          headers: <String, String>{'Idempotency-Key': 'intent-1'},
        );

        expect(transport.lastRequest!.method, ApiHttpMethod.post);
        expect(transport.lastRequest!.headers, <String, String>{
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': 'intent-1',
        });
        final body = transport.lastRequest!.body! as ApiJsonBody;
        expect(utf8.decode(body.bytes), '{"quantity":2,"notes":null}');
      },
    );

    test(
      'required requests obtain a current token and attach it once',
      () async {
        final transport = FakeTransport.success({'id': '1'});
        final client = _client(
          transport,
          authTokenProvider: StubTokenProvider(' clerk-token '),
        );

        await client.get<Object?>('/me', authMode: ApiAuthMode.required);

        expect(
          transport.lastRequest!.headers['Authorization'],
          'Bearer clerk-token',
        );
      },
    );

    test('public requests do not invoke a token provider', () async {
      final provider = StubTokenProvider('unused');
      final client = _client(
        FakeTransport.success({'id': '1'}),
        authTokenProvider: provider,
      );

      await client.get<Object?>('/products');

      expect(provider.calls, 0);
    });

    test(
      'required requests fail locally when the token is unavailable',
      () async {
        final transport = FakeTransport.success({'id': '1'});
        final client = _client(
          transport,
          authTokenProvider: StubTokenProvider(null),
        );

        await expectLater(
          client.get<Object?>('/me', authMode: ApiAuthMode.required),
          throwsA(isA<ApiAuthenticationException>()),
        );
        expect(transport.sendCount, 0);
      },
    );

    test('caller cannot override transport-owned headers', () async {
      final client = _client(FakeTransport.success({'id': '1'}));

      await expectLater(
        client.get<Object?>(
          '/products',
          headers: <String, String>{'Authorization': 'Bearer forged'},
        ),
        throwsA(isA<ApiConfigurationException>()),
      );
    });
  });

  group('success parsing', () {
    test('parses resource and collection metadata', () async {
      final transport = FakeTransport.json(
        200,
        <String, Object?>{
          'data': <String, Object?>{'id': '1'},
          'meta': <String, Object?>{
            'request_id': 'req-body',
            'pagination': <String, Object?>{
              'current_page': 1,
              'per_page': 20,
              'total': 1,
              'last_page': 1,
              'has_next': false,
              'has_previous': false,
            },
          },
        },
        headers: <String, String>{'X-Request-Id': 'req-header'},
      );
      final client = _client(transport);

      final response = await client.get<Map<String, Object?>>(
        '/products',
        decoder: (value) => Map<String, Object?>.from(value! as Map),
      );

      expect(response!.data['id'], '1');
      expect(response.meta!.requestId, 'req-body');
      expect(response.meta!.pagination!.perPage, 20);
    });

    test('uses the response header as request ID fallback', () async {
      final client = _client(
        FakeTransport.json(
          200,
          <String, Object?>{'data': <String, Object?>{}},
          headers: <String, String>{'X-Request-Id': 'req-header'},
        ),
      );

      final response = await client.get<Object?>('/products');

      expect(response!.meta!.requestId, 'req-header');
    });

    test('handles documented 204 without fabricating an envelope', () async {
      final client = _client(FakeTransport.raw(204, Uint8List(0)));

      expect(await client.delete<Object?>('/requests/REQ-1'), isNull);
    });

    test('rejects malformed success responses', () async {
      final client = _client(FakeTransport.raw(200, utf8.encode('{bad')));

      await expectLater(
        client.get<Object?>('/products'),
        throwsA(
          isA<ApiError>().having(
            (error) => error.invalidResponse,
            'invalid',
            true,
          ),
        ),
      );
    });

    test('rejects unsupported response content types', () async {
      final client = _client(
        FakeTransport.raw(
          200,
          utf8.encode('<html>error</html>'),
          headers: <String, String>{'Content-Type': 'text/html'},
        ),
      );

      await expectLater(
        client.get<Object?>('/products'),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.unsupportedContentType,
          ),
        ),
      );
    });
  });

  group('API errors and resilience', () {
    test(
      'preserves status, multiple errors, request ID, and retry-after',
      () async {
        final client = _client(
          FakeTransport.json(
            422,
            <String, Object?>{
              'errors': <Object?>[
                <String, Object?>{
                  'code': 'INVALID_VALUE',
                  'message': 'Invalid value.',
                  'field': 'dimensions.width',
                  'details': <String, Object?>{'min': 1},
                },
                <String, Object?>{
                  'code': 'MISSING_REQUIRED_FIELD',
                  'message': 'Required.',
                },
              ],
              'meta': <String, Object?>{'request_id': 'req-422'},
            },
            headers: <String, String>{
              'Retry-After': '60',
              'X-Request-Id': 'req-header',
            },
          ),
        );

        final error = await _captureApiError(
          () => client.post<Object?>('/requests'),
        );

        expect(error.statusCode, 422);
        expect(error.requestId, 'req-422');
        expect(error.retryAfterSeconds, 60);
        expect(error.errors, hasLength(2));
        expect(error.errors.first.field, 'dimensions.width');
        expect(error.errors.first.details!['min'], 1);
      },
    );

    test('keeps 401 and 403 distinct', () async {
      for (final status in <int>[401, 403]) {
        final client = _client(
          FakeTransport.json(status, <String, Object?>{
            'errors': <Object?>[
              <String, Object?>{'code': 'AUTH_ERROR', 'message': 'Denied.'},
            ],
            'meta': <String, Object?>{'request_id': 'req-$status'},
          }),
        );

        final error = await _captureApiError(() => client.get<Object?>('/me'));

        expect(error.statusCode, status);
      }
    });

    test('does not retry a failed request', () async {
      final transport = FakeTransport.json(500, <String, Object?>{
        'errors': <Object?>[
          <String, Object?>{
            'code': 'INTERNAL_SERVER_ERROR',
            'message': 'Unavailable.',
          },
        ],
        'meta': <String, Object?>{'request_id': 'req-500'},
      });
      final client = _client(transport);

      await expectLater(
        client.get<Object?>('/products'),
        throwsA(isA<ApiError>()),
      );
      expect(transport.sendCount, 1);
    });

    test('distinguishes explicit cancellation from timeout', () async {
      final cancellation = RequestCancellation();
      final transport = FakeTransport.waitForAbort();
      final client = _client(transport);
      final request = client.get<Object?>(
        '/products',
        cancellation: cancellation,
        timeout: const Duration(seconds: 1),
      );
      cancellation.cancel();
      await expectLater(
        request,
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.cancellation,
          ),
        ),
      );

      final timeoutClient = _client(FakeTransport.waitForAbort());
      await expectLater(
        timeoutClient.get<Object?>(
          '/products',
          timeout: const Duration(milliseconds: 1),
        ),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.timeout,
          ),
        ),
      );
    });

    test('does not expose lower-level exception text', () async {
      final client = _client(FakeTransport.failure('token-or-url-secret'));

      final error = await _captureTransportError(
        () => client.get<Object?>('/products'),
      );

      expect(error.toString(), isNot(contains('token-or-url-secret')));
    });
  });

  group('package:http transport', () {
    test('disables redirects and uses abortable JSON requests', () async {
      final httpClient = RecordingHttpClient(
        body: utf8.encode('{"data":{"ok":true}}'),
      );
      final transport = HttpApiTransport(client: httpClient);

      await transport.send(
        ApiTransportRequest(
          method: ApiHttpMethod.post,
          uri: Uri.parse('https://api.example.test/api/v1/requests'),
          headers: const <String, String>{
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: ApiJsonBody(Uint8List.fromList(utf8.encode('{"ok":true}'))),
          abortTrigger: Future<void>.value(),
        ),
      );

      expect(httpClient.lastRequest, isA<http.AbortableRequest>());
      expect(httpClient.lastRequest!.followRedirects, isFalse);
      expect(httpClient.lastRequest!.maxRedirects, 0);
      expect(httpClient.lastRequest!.headers['Authorization'], isNull);
    });
  });
}

ApiClient _client(
  FakeTransport transport, {
  AuthTokenProvider? authTokenProvider,
}) => ApiClient(
  config: const AppConfig(
    environment: AppEnvironment.local,
    apiBaseUrl: 'http://127.0.0.1:8000',
  ),
  transport: transport,
  authTokenProvider: authTokenProvider,
);

Future<ApiError> _captureApiError(Future<Object?> Function() operation) async {
  try {
    await operation();
  } on ApiError catch (error) {
    return error;
  }
  fail('Expected ApiError');
}

Future<ApiTransportException> _captureTransportError(
  Future<Object?> Function() operation,
) async {
  try {
    await operation();
  } on ApiTransportException catch (error) {
    return error;
  }
  fail('Expected ApiTransportException');
}

class StubTokenProvider implements AuthTokenProvider {
  StubTokenProvider(this.token);

  final String? token;
  int calls = 0;

  @override
  Future<String?> getToken() async {
    calls++;
    return token;
  }
}

class FakeTransport implements ApiTransport {
  FakeTransport(this._handler);

  factory FakeTransport.success(Map<String, Object?> data) => FakeTransport(
    (_) async => _jsonResponse(200, <String, Object?>{'data': data}),
  );

  factory FakeTransport.json(
    int status,
    Map<String, Object?> payload, {
    Map<String, String> headers = const {},
  }) => FakeTransport(
    (_) async => _jsonResponse(status, payload, headers: headers),
  );

  factory FakeTransport.raw(
    int status,
    List<int> bytes, {
    Map<String, String> headers = const {},
  }) => FakeTransport(
    (_) async => ApiTransportResponse(
      statusCode: status,
      headers: headers,
      bodyBytes: Uint8List.fromList(bytes),
    ),
  );

  factory FakeTransport.waitForAbort() => FakeTransport((request) async {
    await request.abortTrigger;
    throw StateError('aborted by test');
  });

  factory FakeTransport.failure(String message) => FakeTransport(
    (_) => Future<ApiTransportResponse>.error(StateError(message)),
  );

  final Future<ApiTransportResponse> Function(ApiTransportRequest) _handler;
  ApiTransportRequest? lastRequest;
  int sendCount = 0;

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest request) {
    lastRequest = request;
    sendCount++;
    return _handler(request);
  }

  @override
  Future<void> close() async {}
}

class RecordingHttpClient extends http.BaseClient {
  RecordingHttpClient({required this.body});

  final List<int> body;
  http.BaseRequest? lastRequest;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    lastRequest = request;
    return http.StreamedResponse(
      Stream<List<int>>.value(body),
      200,
      headers: const <String, String>{'Content-Type': 'application/json'},
    );
  }
}

ApiTransportResponse _jsonResponse(
  int status,
  Map<String, Object?> payload, {
  Map<String, String> headers = const {},
}) => ApiTransportResponse(
  statusCode: status,
  headers: <String, String>{'Content-Type': 'application/json', ...headers},
  bodyBytes: Uint8List.fromList(utf8.encode(jsonEncode(payload))),
);
