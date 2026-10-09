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
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';

void main() {
  group('CAT-001 repository', () {
    test('reads the collection path without a bearer token', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCatalogRepository(
        _client(transport, token: ' clerk-session-token '),
      );

      await repository.fetchProducts(const CatalogQuery());

      expect(transport.lastRequest!.method, ApiHttpMethod.get);
      expect(transport.lastRequest!.uri.path, '/api/v1/products');
      expect(
        transport.lastRequest!.headers.containsKey('Authorization'),
        isFalse,
        reason: 'Catalog search is public and must never carry a token.',
      );
    });

    test('encodes combined search, category, sort, and pagination', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCatalogRepository(_client(transport));

      await repository.fetchProducts(
        const CatalogQuery(
          search: 'walnut sofa',
          categorySlug: 'living-room',
          sort: CatalogSort.priceLowToHigh,
          page: 3,
          perPage: 10,
        ),
      );

      expect(transport.lastRequest!.uri.queryParameters, <String, String>{
        'search': 'walnut sofa',
        'category': 'living-room',
        'sort': 'price',
        'sort_direction': 'asc',
        'product_type': 'MADE_TO_ORDER',
        'page': '3',
        'per_page': '10',
      });
    });

    test('never sends a parameter outside the contract allow-list', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCatalogRepository(_client(transport));

      await repository.fetchProducts(
        const CatalogQuery(search: 'sofa', sort: CatalogSort.nameAToZ),
      );

      final allowed = <String>{
        'search',
        'category',
        'product_type',
        'sort',
        'sort_direction',
        'page',
        'per_page',
      };
      expect(
        transport.lastRequest!.uri.queryParameters.keys.where(
          (key) => !allowed.contains(key),
        ),
        isEmpty,
        reason: 'Laravel answers 422 INVALID_VALUE for an unknown parameter.',
      );
    });

    test('decodes summaries and preserves pagination metadata', () async {
      final transport = _FakeTransport.json(
        _collection(
          <Object?>[_product()],
          currentPage: 2,
          total: 48,
          lastPage: 3,
        ),
      );
      final repository = ApiCatalogRepository(_client(transport));

      final page = await repository.fetchProducts(
        const CatalogQuery(page: 2, perPage: 20),
      );

      expect(page.products.single.slug, 'nordic-3-seater-sofa');
      expect(page.products.single.price.amount, 125000000);
      expect(page.pagination.total, 48);
      expect(page.pagination.lastPage, 3);
      expect(page.pagination.currentPage, 2);
      expect(page.pagination.hasNext, isTrue);
      expect(page.pagination.hasPrevious, isTrue);
    });

    test('rejects a success envelope with no pagination metadata', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'data': <Object?>[],
      });
      final repository = ApiCatalogRepository(_client(transport));

      await expectLater(
        repository.fetchProducts(const CatalogQuery()),
        throwsA(isA<FormatException>()),
      );
    });

    test('preserves a 422 validation failure with its field', () async {
      final transport = _FakeTransport.json(<String, Object?>{
        'errors': <Object?>[
          <String, Object?>{
            'code': 'INVALID_VALUE',
            'message': 'The sort is invalid.',
            'field': 'sort',
          },
        ],
        'meta': <String, Object?>{'request_id': 'req-422'},
      }, status: 422);
      final repository = ApiCatalogRepository(_client(transport));

      final error = await _captureApiError(
        () => repository.fetchProducts(const CatalogQuery()),
      );

      expect(error.statusCode, 422);
      expect(error.errors.single.field, 'sort');
      expect(error.requestId, 'req-422');
    });

    test('preserves transport failures and never retries', () async {
      final transport = _FakeTransport(
        (_) => Future<ApiTransportResponse>.error(StateError('reset')),
      );
      final repository = ApiCatalogRepository(_client(transport));

      await expectLater(
        repository.fetchProducts(const CatalogQuery()),
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

    test('honors cancellation before sending anything', () async {
      final transport = _FakeTransport.json(_collection(<Object?>[]));
      final repository = ApiCatalogRepository(_client(transport));
      final cancellation = RequestCancellation()..cancel();

      await expectLater(
        repository.fetchProducts(
          const CatalogQuery(),
          cancellation: cancellation,
        ),
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

Map<String, Object?> _product() => <String, Object?>{
  'id': 'prod_01h8x9j2m4k5n6p7q8r9s0t1',
  'name': 'Nordic 3-Seater Sofa',
  'slug': 'nordic-3-seater-sofa',
  'product_type': 'MADE_TO_ORDER',
  'price': <String, Object?>{'amount': 125000000, 'currency': 'TZS'},
  'category': <String, Object?>{
    'id': 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
    'slug': 'sofas',
    'name': 'Sofas',
  },
  'primary_image': <String, Object?>{
    'id': 'img_01h8x9a0b1c2d3e4f5g6h7j8',
    'url': 'https://cdn.example.test/sofa.webp',
    'alt_text': 'A grey fabric sofa',
  },
  'availability': 'available',
  'stock_indicator': 'MADE_TO_ORDER',
};

Map<String, Object?> _collection(
  List<Object?> data, {
  int currentPage = 1,
  int total = 0,
  int lastPage = 1,
}) => <String, Object?>{
  'data': data,
  'meta': <String, Object?>{
    'pagination': <String, Object?>{
      'current_page': currentPage,
      'per_page': 20,
      'total': total,
      'last_page': lastPage,
      'has_next': currentPage < lastPage,
      'has_previous': currentPage > 1,
    },
  },
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
