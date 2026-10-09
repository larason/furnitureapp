import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';

void main() {
  group('CAT-003 category collection', () {
    test('performs a public GET without a bearer token', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCategoryRepository(
        _client(transport, token: ' clerk-session-token '),
      );

      await repository.getCategories(page: 1);

      expect(transport.lastRequest!.method, ApiHttpMethod.get);
      expect(transport.lastRequest!.headers, <String, String>{
        'Accept': 'application/json',
      });
      expect(transport.lastRequest!.uri.queryParameters, <String, String>{
        'page': '1',
        'per_page': '20',
      });
    });

    test('decodes the CAT-003 response', () async {
      final transport = _FakeTransport.json(
        _collection(<Object?>[
          <String, Object?>{
            'id': 'cat_living',
            'name': 'Living Room',
            'slug': 'living-room',
            'image': <String, Object?>{
              'url': 'https://cdn.example.test/categories/living-room.webp',
            },
          },
          <String, Object?>{
            'id': 'cat_bedroom',
            'name': 'Bedroom',
            'slug': 'bedroom',
            'image': null,
          },
        ], total: 2),
      );
      final repository = ApiCategoryRepository(_client(transport));

      final page = await repository.getCategories(page: 1);

      expect(page.categories.map((category) => category.slug), <String>[
        'living-room',
        'bedroom',
      ]);
      expect(page.categories.first.image, isNotNull);
      expect(page.categories.last.image, isNull);
      expect(page.categories.any((category) => category.id.isEmpty), isFalse);
    });

    test('preserves collection pagination metadata', () async {
      final transport = _FakeTransport.json(
        _collection(<Object?>[], page: 3, perPage: 2, total: 7, lastPage: 4),
      );
      final repository = ApiCategoryRepository(_client(transport));

      final page = await repository.getCategories(page: 3);

      expect(page.pagination.currentPage, 3);
      expect(page.pagination.perPage, 2);
      expect(page.pagination.total, 7);
      expect(page.pagination.lastPage, 4);
      expect(page.pagination.hasNext, isTrue);
      expect(page.pagination.hasPrevious, isTrue);
    });

    test('decodes an empty collection', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[], total: 0));
      final repository = ApiCategoryRepository(_client(transport));

      final page = await repository.getCategories(page: 1);

      expect(page.categories, isEmpty);
      expect(page.pagination.total, 0);
      expect(page.pagination.hasNext, isFalse);
    });

    test('rejects a collection without pagination', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <Object?>[],
        'meta': <String, Object?>{},
      });
      final repository = ApiCategoryRepository(_client(transport));

      await expectLater(
        repository.getCategories(page: 1),
        throwsA(isA<FormatException>()),
      );
    });

    test('rejects a non-list collection payload', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <String, Object?>{},
        'meta': _pagination(),
      });
      final repository = ApiCategoryRepository(_client(transport));

      await expectLater(
        repository.getCategories(page: 1),
        throwsA(
          isA<ApiError>().having(
            (error) => error.invalidResponse,
            'invalid',
            isTrue,
          ),
        ),
      );
    });
  });

  group('CAT-004 category detail', () {
    test('decodes the detail response', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
          'description': 'Sofas and seating.',
          'image': <String, Object?>{
            'url': 'https://cdn.example.test/categories/living-room.webp',
          },
          'created_at': '2026-08-20T08:00:00Z',
        },
      });
      final repository = ApiCategoryRepository(_client(transport));

      final detail = await repository.getCategory('living-room');

      expect(detail.slug, 'living-room');
      expect(detail.description, 'Sofas and seating.');
      expect(detail.createdAt, DateTime.utc(2026, 8, 20, 8));
      expect(transport.lastRequest!.uri.path, '/api/v1/categories/living-room');
    });

    test('sends no bearer token for a public detail read', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        },
      });
      final repository = ApiCategoryRepository(
        _client(transport, token: ' clerk-session-token '),
      );

      await repository.getCategory('living-room');

      expect(
        transport.lastRequest!.headers.containsKey('Authorization'),
        isFalse,
      );
    });

    test('encodes the route identifier', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        },
      });
      final repository = ApiCategoryRepository(_client(transport));

      await repository.getCategory('cat_01h8x8a1b2c3d4e5f6g7h8j9');

      expect(
        transport.lastRequest!.uri.path,
        '/api/v1/categories/cat_01h8x8a1b2c3d4e5f6g7h8j9',
      );
    });

    test('rejects a malformed identifier before any request', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        },
      });
      final repository = ApiCategoryRepository(_client(transport));

      await expectLater(
        repository.getCategory('../users'),
        throwsA(isA<FormatException>()),
      );
      expect(transport.sendCount, 0);
    });

    test('preserves RESOURCE_NOT_FOUND for a missing category', () async {
      final transport = _FakeTransport.json(
        _error(404, 'RESOURCE_NOT_FOUND'),
        status: 404,
      );
      final repository = ApiCategoryRepository(_client(transport));

      final error = await _captureApiError(
        () => repository.getCategory('missing-room'),
      );

      expect(error.statusCode, 404);
      expect(error.errors.single.code, 'RESOURCE_NOT_FOUND');
      expect(error.requestId, 'req-404');
    });

    test('preserves structured API errors and request IDs', () async {
      final transport = _FakeTransport.json(
        _error(422, 'INVALID_VALUE'),
        status: 422,
      );
      final repository = ApiCategoryRepository(_client(transport));

      final error = await _captureApiError(
        () => repository.getCategory('living-room'),
      );

      expect(error.statusCode, 422);
      expect(error.errors.single.field, 'page');
      expect(error.requestId, 'req-422');
    });

    test('preserves transport failures', () async {
      final transport = _FakeTransport(
        (_) =>
            Future<ApiTransportResponse>.error(StateError('connection reset')),
      );
      final repository = ApiCategoryRepository(_client(transport));

      await expectLater(
        repository.getCategories(page: 1),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.connection,
          ),
        ),
      );
      expect(transport.sendCount, 1);
    });

    test('honors cancellation without sending a request', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCategoryRepository(_client(transport));
      final cancellation = RequestCancellation()..cancel();

      await expectLater(
        repository.getCategories(page: 1, cancellation: cancellation),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.cancellation,
          ),
        ),
      );
      expect(transport.sendCount, 0);
    });

    test('never retries a failed request', () async {
      final transport = _FakeTransport.json(
        _error(500, 'INTERNAL_ERROR'),
        status: 500,
      );
      final repository = ApiCategoryRepository(_client(transport));

      await expectLater(
        repository.getCategories(page: 1),
        throwsA(isA<ApiError>()),
      );
      expect(transport.sendCount, 1);
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

Map<String, Object?> _pagination({
  int page = 1,
  int perPage = 20,
  int total = 0,
  int lastPage = 1,
}) => <String, Object?>{
  'current_page': page,
  'per_page': perPage,
  'total': total,
  'last_page': lastPage,
  'has_next': page < lastPage,
  'has_previous': page > 1,
};

Map<String, Object?> _collection(
  List<Object?> data, {
  int page = 1,
  int perPage = 20,
  int total = 0,
  int lastPage = 1,
}) => <String, Object?>{
  'data': data,
  'meta': <String, Object?>{
    'pagination': _pagination(
      page: page,
      perPage: perPage,
      total: total,
      lastPage: lastPage,
    ),
  },
};

Map<String, Object?> _error(int status, String code) => <String, Object?>{
  'errors': <Object?>[
    <String, Object?>{
      'code': code,
      'message': 'The request could not be completed.',
      if (status == 422) 'field': 'page',
    },
  ],
  'meta': <String, Object?>{'request_id': 'req-$status'},
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
