import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail_repository.dart';

import '../../support/cat002_payload.dart';

void main() {
  group('CAT-002 product detail', () {
    test('reads the canonical slug path without a bearer token', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': cat002Product(),
      });
      final repository = ApiProductDetailRepository(
        cat002Client(transport, token: ' clerk-session-token '),
      );

      final detail = await repository.getProduct('modern-3-seater-fabric-sofa');

      expect(transport.lastRequest!.method.name, 'get');
      expect(
        transport.lastRequest!.headers.containsKey('Authorization'),
        isFalse,
      );
      expect(
        transport.lastRequest!.uri.path,
        '/api/v1/products/modern-3-seater-fabric-sofa',
      );
      expect(transport.lastRequest!.uri.query, isEmpty);
      expect(detail.slug, 'modern-3-seater-fabric-sofa');
    });

    test('reads a stable opaque product ID through the same path', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': cat002Product(),
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await repository.getProduct('prod_01h8x9j2m4k5n6p7q8r9s0t1');

      expect(
        transport.lastRequest!.uri.path,
        '/api/v1/products/prod_01h8x9j2m4k5n6p7q8r9s0t1',
      );
    });

    test('makes exactly one request and never calls CAT-005', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': cat002Product(),
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await repository.getProduct('modern-3-seater-fabric-sofa');

      expect(transport.sendCount, 1);
      expect(transport.requests.map((request) => request.uri.path), <String>[
        '/api/v1/products/modern-3-seater-fabric-sofa',
      ]);
    });

    test('rejects a malformed identifier before any request', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': cat002Product(),
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await expectLater(
        repository.getProduct('../users'),
        throwsA(isA<FormatException>()),
      );
      expect(transport.sendCount, 0);
    });

    test('preserves RESOURCE_NOT_FOUND for a non-public product', () async {
      final transport = Cat002Transport.json(
        cat002Error(404, 'RESOURCE_NOT_FOUND'),
        status: 404,
      );
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await expectLater(
        repository.getProduct('missing-sofa'),
        throwsA(
          isA<ApiError>()
              .having((error) => error.statusCode, 'status', 404)
              .having(
                (error) => error.errors.single.code,
                'code',
                'RESOURCE_NOT_FOUND',
              ),
        ),
      );
    });

    test('preserves transport failures without retrying', () async {
      final transport = Cat002Transport(
        (_) => Future<ApiTransportResponse>.error(StateError('reset')),
      );
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await expectLater(
        repository.getProduct('modern-3-seater-fabric-sofa'),
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

    test('rejects an invalid response envelope', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'items': <Object?>[],
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await expectLater(
        repository.getProduct('modern-3-seater-fabric-sofa'),
        throwsA(isA<ApiTransportException>()),
      );
    });

    test('rejects a success envelope whose data is not a product', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': <String, Object?>{'id': 'prod_1'},
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));

      await expectLater(
        repository.getProduct('modern-3-seater-fabric-sofa'),
        throwsA(
          isA<ApiError>().having(
            (error) => error.invalidResponse,
            'invalidResponse',
            isTrue,
          ),
        ),
      );
    });

    test('honors cancellation before a request is sent', () async {
      final transport = Cat002Transport.json(<String, Object?>{
        'data': cat002Product(),
      });
      final repository = ApiProductDetailRepository(cat002Client(transport));
      final cancellation = RequestCancellation()..cancel();

      await expectLater(
        repository.getProduct(
          'modern-3-seater-fabric-sofa',
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
