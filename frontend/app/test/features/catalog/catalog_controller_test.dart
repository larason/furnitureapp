import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/presentation/async_view_state.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/catalog/presentation/catalog_controller.dart';

void main() {
  test('refresh invalidates a pending next-page result', () async {
    final repository = _DelayedCatalogRepository();
    final controller = CatalogController(repository);
    addTearDown(controller.dispose);

    await controller.load();
    final nextPage = controller.loadNextPage();
    await Future<void>.delayed(Duration.zero);
    await controller.refresh();

    repository.nextPage.complete(_page('stale_page', hasNext: false));
    await nextPage;

    final state = controller.state;
    expect(state, isA<AsyncContent<CatalogPage>>());
    expect(
      (state as AsyncContent<CatalogPage>).data.products.single.id,
      'refreshed',
    );
  });

  test('refresh failure retains products for an inline retry', () async {
    final repository = _FailingRefreshRepository();
    final controller = CatalogController(repository);
    addTearDown(controller.dispose);

    await controller.load();
    await controller.refresh();

    final state = controller.state;
    expect(state, isA<AsyncFailure<CatalogPage>>());
    expect(
      (state as AsyncFailure<CatalogPage>).previousData!.products.single.id,
      'initial',
    );
  });
}

class _DelayedCatalogRepository implements CatalogRepository {
  final nextPage = Completer<CatalogPage>();
  int pageOneCalls = 0;

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) {
    if (query.page == 2) return nextPage.future;
    pageOneCalls++;
    return Future.value(
      _page(pageOneCalls == 1 ? 'initial' : 'refreshed', hasNext: true),
    );
  }
}

class _FailingRefreshRepository implements CatalogRepository {
  int pageOneCalls = 0;

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    pageOneCalls++;
    if (pageOneCalls == 2) throw StateError('refresh failed');
    return _page('initial', hasNext: false);
  }
}

CatalogPage _page(String id, {required bool hasNext}) => CatalogPage(
  products: <ProductSummary>[_product(id)],
  pagination: ApiPagination(
    currentPage: 1,
    perPage: 20,
    total: hasNext ? 2 : 1,
    lastPage: hasNext ? 2 : 1,
    hasNext: hasNext,
    hasPrevious: false,
  ),
);

ProductSummary _product(String id) => ProductSummary(
  id: id,
  slug: id,
  name: id,
  productType: 'MADE_TO_ORDER',
  price: const Money(amount: 199, currency: 'TZS'),
  category: const ProductCategorySummary(
    id: 'living',
    slug: 'living-room',
    name: 'Living Room',
  ),
  primaryImage: null,
  availability: 'available',
  stockIndicator: 'MADE_TO_ORDER',
);
