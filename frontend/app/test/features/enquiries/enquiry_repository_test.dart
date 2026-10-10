import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/attachments/pending_attachment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_draft.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_repository.dart';

const _anonymousDraft = EnquiryDraft(
  subject: 'Do you deliver to Dodoma?',
  message: 'I would like to know whether you deliver to Dodoma.',
  name: 'Asha Mushi',
  phone: '+255700000000',
);

void main() {
  group('ENQ-001 request', () {
    test('posts to the frozen enquiries endpoint', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      await repository.submit(_anonymousDraft, authenticated: false);

      expect(transport.lastRequest!.method, ApiHttpMethod.post);
      expect(transport.lastRequest!.uri.path, '/api/v1/enquiries');
    });

    test('sends an anonymous enquiry with no bearer token', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(
        _client(transport, token: 'clerk-session-token'),
      );

      await repository.submit(_anonymousDraft, authenticated: false);

      expect(transport.lastRequest!.headers, isNot(contains('Authorization')));
      expect(
        transport.lastRequest!.headers['Content-Type'],
        'application/json',
      );
    });

    test('sends the bearer token when authenticated', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(
        _client(transport, token: 'clerk-session-token'),
      );

      await repository.submit(_anonymousDraft, authenticated: true);

      expect(
        transport.lastRequest!.headers['Authorization'],
        'Bearer clerk-session-token',
      );
    });

    test('encodes a trimmed JSON body with the allow-listed fields', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      await repository.submit(
        _anonymousDraft.copyWith(
          subject: '  Do you deliver to Dodoma?  ',
          email: '  ',
          product: const EnquiryProductContext(id: 'prod_01', name: 'Sofa'),
        ),
        authenticated: false,
      );

      expect(_jsonBody(transport), <String, Object?>{
        'subject': 'Do you deliver to Dodoma?',
        'message': 'I would like to know whether you deliver to Dodoma.',
        'name': 'Asha Mushi',
        'phone': '+255700000000',
        'product_id': 'prod_01',
      });
    });

    test('never sends server-controlled or prohibited fields', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(
        _client(transport, token: 'clerk-session-token'),
      );

      await repository.submit(_anonymousDraft, authenticated: true);

      for (final prohibited in <String>[
        'user_id',
        'enquiry_status',
        'staff_internal_notes',
        'internal_notes',
        'order_id',
        'created_at',
        'updated_at',
        'reference',
        'payment_id',
        'payment_status',
        'order_status',
        'create_order',
      ]) {
        expect(
          _jsonBody(transport),
          isNot(contains(prohibited)),
          reason: 'ENQ-001 must never send "$prohibited"',
        );
      }
    });

    test('decodes the created enquiry', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      final submitted = await repository.submit(
        _anonymousDraft,
        authenticated: false,
      );

      expect(submitted.id, 'enq_01h8y5a1b2c3d4e5f6g7h8j9');
      expect(submitted.status, 'OPEN');
    });
  });

  group('ENQ-001 multipart', () {
    test('uses the canonical attachment field', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      await repository.submit(
        _anonymousDraft.copyWith(attachment: _attachment()),
        authenticated: false,
      );

      final body = transport.lastRequest!.body! as ApiMultipartBody;
      expect(body.files.single.field, 'attachment');
      expect(body.files.single.filename, 'reference.png');
      expect(body.files.single.contentType, 'image/png');
      expect(body.files.single.bytes, Uint8List.fromList(<int>[1, 2, 3]));
    });

    test('carries the same allow-listed fields as the JSON body', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      await repository.submit(
        _anonymousDraft.copyWith(
          email: 'asha@example.com',
          attachment: _attachment(),
        ),
        authenticated: false,
      );

      final body = transport.lastRequest!.body! as ApiMultipartBody;
      expect(body.fields, <String, String>{
        'subject': 'Do you deliver to Dodoma?',
        'message': 'I would like to know whether you deliver to Dodoma.',
        'name': 'Asha Mushi',
        'phone': '+255700000000',
        'email': 'asha@example.com',
      });
    });

    test('does not upload an attachment to a separate endpoint', () async {
      final transport = _FakeTransport.json(_created());
      final repository = ApiEnquiryRepository(_client(transport));

      await repository.submit(
        _anonymousDraft.copyWith(attachment: _attachment()),
        authenticated: false,
      );

      expect(transport.sendCount, 1);
    });
  });

  group('ENQ-001 failures', () {
    test('maps field-level validation errors', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'errors': <Object?>[
          <String, Object?>{
            'code': 'INVALID_VALUE',
            'message':
                'The subject field must be between 5 and 200 characters.',
            'field': 'subject',
          },
        ],
        'meta': <String, Object?>{'request_id': 'req-422'},
      }, status: 422);
      final repository = ApiEnquiryRepository(_client(transport));

      final error = await _captureApiError(
        () => repository.submit(_anonymousDraft, authenticated: false),
      );

      expect(error.statusCode, 422);
      expect(error.errors.single.field, 'subject');
      expect(error.requestId, 'req-422');
    });

    test('exposes rate limiting with its Retry-After value', () async {
      final transport = _FakeTransport(
        (_) async => ApiTransportResponse(
          statusCode: 429,
          headers: const <String, String>{
            'Content-Type': 'application/json',
            'Retry-After': '60',
          },
          bodyBytes: Uint8List.fromList(
            utf8.encode(
              jsonEncode(<String, Object?>{
                'errors': <Object?>[
                  <String, Object?>{
                    'code': 'RATE_LIMITED',
                    'message': 'Too many requests.',
                  },
                ],
                'meta': <String, Object?>{'request_id': 'req-429'},
              }),
            ),
          ),
        ),
      );
      final repository = ApiEnquiryRepository(_client(transport));

      final error = await _captureApiError(
        () => repository.submit(_anonymousDraft, authenticated: false),
      );

      expect(error.statusCode, 429);
      expect(error.retryAfterSeconds, 60);
    });

    test('surfaces an authentication failure', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'errors': <Object?>[
          <String, Object?>{
            'code': 'AUTHENTICATION_REQUIRED',
            'message': 'Authentication is required.',
          },
        ],
        'meta': <String, Object?>{'request_id': 'req-401'},
      }, status: 401);
      final repository = ApiEnquiryRepository(
        _client(transport, token: 'clerk-session-token'),
      );

      final error = await _captureApiError(
        () => repository.submit(_anonymousDraft, authenticated: true),
      );

      expect(error.statusCode, 401);
    });

    test('surfaces a transport failure without retrying', () async {
      final transport = _FakeTransport(
        (_) async => throw const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final repository = ApiEnquiryRepository(_client(transport));

      await expectLater(
        repository.submit(_anonymousDraft, authenticated: false),
        throwsA(isA<ApiTransportException>()),
      );
      expect(transport.sendCount, 1);
    });

    test('rejects a response that is not the documented envelope', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'unexpected': true,
      });
      final repository = ApiEnquiryRepository(_client(transport));

      await expectLater(
        repository.submit(_anonymousDraft, authenticated: false),
        throwsA(isA<ApiTransportException>()),
      );
    });
  });
}

ApiClient _client(_FakeTransport transport, {String? token}) => ApiClient(
  config: const AppConfig(
    environment: AppEnvironment.local,
    apiBaseUrl: 'http://127.0.0.1:8000',
  ),
  transport: transport,
  authTokenProvider: token == null ? null : _TokenProvider(token),
);

PendingAttachment _attachment() => PendingAttachment(
  name: 'reference.png',
  bytes: Uint8List.fromList(<int>[1, 2, 3]),
  contentType: 'image/png',
);

Map<String, Object?> _jsonBody(_FakeTransport transport) =>
    jsonDecode(utf8.decode((transport.lastRequest!.body! as ApiJsonBody).bytes))
        as Map<String, Object?>;

Map<String, Object?> _created() => <String, Object?>{
  'data': <String, Object?>{
    'id': 'enq_01h8y5a1b2c3d4e5f6g7h8j9',
    'enquiry_status': 'OPEN',
  },
  'meta': <String, Object?>{'request_id': 'req-201'},
};

Future<ApiError> _captureApiError(Future<Object?> Function() operation) async {
  try {
    await operation();
  } on ApiError catch (error) {
    return error;
  }
  return fail('Expected ApiError');
}

class _TokenProvider implements AuthTokenProvider {
  _TokenProvider(this.token);

  final String token;

  @override
  Future<String?> getToken() async => token;
}

class _FakeTransport implements ApiTransport {
  _FakeTransport(this._handler);

  factory _FakeTransport.json(
    Map<String, Object?> payload, {
    int status = 200,
  }) => _FakeTransport(
    (_) async => ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: Uint8List.fromList(utf8.encode(jsonEncode(payload))),
    ),
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
