import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/presentation/async_view_state.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail_repository.dart';
import 'package:sl_furnitures/features/product_detail/presentation/product_detail_controller.dart';

import '../../support/cat002_payload.dart';

void main() {
  group('ProductDetailController', () {
    test('starts in the initial state and requests the product once', () async {
      final repository = _StubProductDetailRepository();
      final subject = controller(repository);

      expect(subject.state, isA<AsyncInitial<ProductDetail>>());

      await subject.load();

      expect(repository.calls, 1);
      expect(repository.identifiers, <String>['modern-3-seater-fabric-sofa']);
    });

    test('exposes the loaded product as content', () async {
      final repository = _StubProductDetailRepository();
      final subject = controller(repository);

      await subject.load();

      expect(subject.state, isA<AsyncContent<ProductDetail>>());
      expect(subject.product?.slug, 'modern-3-seater-fabric-sofa');
      expect(subject.productUnavailable, isFalse);
    });

    test('reports a not-found product as unavailable', () async {
      final repository = _StubProductDetailRepository(
        error: const ApiError(
          statusCode: 404,
          errors: <ApiErrorItem>[
            ApiErrorItem(
              code: 'RESOURCE_NOT_FOUND',
              message: 'The requested product was not found.',
            ),
          ],
        ),
      );
      final subject = controller(repository);

      await subject.load();

      expect(subject.productUnavailable, isTrue);
      expect(subject.state, isA<AsyncFailure<ProductDetail>>());
      expect(subject.product, isNull);
    });

    test('maps a transport failure to a recoverable presentation', () async {
      final repository = _StubProductDetailRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final subject = controller(repository);

      await subject.load();

      expect(subject.productUnavailable, isFalse);
      expect(
        (subject.state as AsyncFailure<ProductDetail>).error.title,
        'Connection problem',
      );
    });

    test(
      'retries explicitly and keeps prior content on refresh failure',
      () async {
        final repository = _StubProductDetailRepository();
        final subject = controller(repository);
        await subject.load();

        repository.error = const ApiTransportException(
          kind: ApiTransportFailureKind.timeout,
        );
        await subject.refresh();

        expect(repository.calls, 2);
        expect(
          (subject.state as AsyncFailure<ProductDetail>).previousData?.slug,
          'modern-3-seater-fabric-sofa',
          reason: 'A refresh failure must not erase a valid product.',
        );
      },
    );

    test('ignores a stale response that resolves after a newer load', () async {
      final repository = _StubProductDetailRepository(deferFirstCall: true)
        ..supersededDetail = ProductDetail.fromJson(
          cat002Product(slug: 'stale-sofa', name: 'Stale Sofa'),
        );
      final subject = controller(repository);

      final stale = subject.load();
      final current = subject.refresh();
      await current;
      repository.firstCallGate.complete();
      await stale;

      expect(repository.calls, 2);
      expect(
        repository.delivered,
        <String>['modern-3-seater-fabric-sofa', 'stale-sofa'],
        reason:
            'The stale payload must really arrive before it can be discarded.',
      );
      expect(subject.state, isA<AsyncContent<ProductDetail>>());
      expect(
        subject.product?.slug,
        'modern-3-seater-fabric-sofa',
        reason: 'A superseded response must never overwrite the newer product.',
      );
    });

    test('does not surface cancellation as a failure', () async {
      final repository = _StubProductDetailRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.cancellation,
        ),
      );
      final subject = controller(repository);

      await subject.load();

      expect(subject.state, isA<AsyncLoading<ProductDetail>>());
    });

    test('selects a variant and swaps the displayed price', () async {
      final subject = controller(_StubProductDetailRepository());
      await subject.load();

      expect(subject.selectedVariantId, isNull);
      expect(subject.isVariantPrice, isFalse);
      expect(subject.displayedPrice?.amount, 125000000);

      subject.selectVariant('var_beige');

      expect(subject.selectedVariantId, 'var_beige');
      expect(subject.isVariantPrice, isTrue);
      expect(subject.displayedPrice?.amount, 128000000);
    });

    test('ignores a variant that does not belong to the product', () async {
      final subject = controller(_StubProductDetailRepository());
      await subject.load();

      subject.selectVariant('var_not_in_payload');

      expect(subject.selectedVariantId, isNull);
      expect(subject.displayedPrice?.amount, 125000000);
    });

    test('clears the selection with null', () async {
      final subject = controller(_StubProductDetailRepository());
      await subject.load();
      subject.selectVariant('var_grey');

      subject.selectVariant(null);

      expect(subject.selectedVariantId, isNull);
      expect(subject.isVariantPrice, isFalse);
    });

    test('resets the selection when a new product is loaded', () async {
      final repository = _StubProductDetailRepository();
      final subject = controller(repository);
      await subject.load();
      subject.selectVariant('var_beige');

      repository.detail = ProductDetail.fromJson(
        cat002Product(slug: 'barrel-chair', variants: <Object?>[]),
      );
      await subject.refresh();

      expect(subject.selectedVariantId, isNull);
      expect(subject.isVariantPrice, isFalse);
      expect(subject.product?.slug, 'barrel-chair');
    });

    test('stops notifying after disposal', () async {
      final repository = _StubProductDetailRepository(pending: true);
      final subject = controller(repository);
      final pending = subject.load();

      subject.dispose();
      repository.gate.complete();
      await pending;

      expect(subject.product, isNull);
    });
  });
}

ProductDetailController controller(ProductDetailRepository repository) =>
    ProductDetailController(
      repository: repository,
      identifier: 'modern-3-seater-fabric-sofa',
    );

class _StubProductDetailRepository implements ProductDetailRepository {
  _StubProductDetailRepository({
    this.error,
    this.pending = false,
    this.deferFirstCall = false,
  });

  ProductDetail detail = ProductDetail.fromJson(cat002Product());
  Object? error;
  final bool pending;

  /// Holds the first call open so its response can arrive after a newer load has
  /// already completed, which is the race stale-response suppression must
  /// survive.
  final bool deferFirstCall;
  final Completer<void> gate = Completer<void>();
  final Completer<void> firstCallGate = Completer<void>();

  /// Response returned by a request that was already answered when it became
  /// superseded. It stands in for a transport that does not honour the abort
  /// signal, so the controller must discard it on its own rather than rely on
  /// the request having been cancelled.
  ProductDetail? supersededDetail;

  final List<String> identifiers = <String>[];

  /// Slug of every payload this stub actually handed back, so a test can prove a
  /// superseded response really was delivered before asserting it was discarded.
  final List<String> delivered = <String>[];
  int calls = 0;

  @override
  Future<ProductDetail> getProduct(
    String product, {
    RequestCancellation? cancellation,
  }) async {
    final call = calls++;
    identifiers.add(product);
    if (call == 0 && deferFirstCall) {
      await firstCallGate.future;
    } else if (pending) {
      await gate.future;
    }
    final superseded = supersededDetail;
    if (cancellation?.isCancelled ?? false) {
      if (superseded != null) {
        delivered.add(superseded.slug);
        return superseded;
      }
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }
    if (error != null) throw error!;
    delivered.add(detail.slug);
    return detail;
  }
}
