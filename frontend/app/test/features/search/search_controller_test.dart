import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/presentation/async_view_state.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/search/presentation/search_controller.dart';

void main() {
  group('SearchController', () {
    test('loads the made-to-order catalog before any criteria exist', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);

      await pumpEventQueue();

      expect(controller.criteria, isUnfilteredPredicate);
      expect(controller.hasActiveCriteria, isFalse);
      expect(controller.searchText, isEmpty);
      expect(catalog.queries.single.normalizedSearch, isNull);
      expect(catalog.queries.single.categorySlug, isNull);
      expect(controller.results.state, isA<AsyncContent<CatalogPage>>());
    });

    test('debounces typing into a single request', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(
        catalog,
        debounce: const Duration(milliseconds: 20),
      );
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();
      final beforeTyping = catalog.queries.length;

      controller
        ..onSearchTextChanged('s')
        ..onSearchTextChanged('so')
        ..onSearchTextChanged('sofa');
      await Future<void>.delayed(const Duration(milliseconds: 60));
      await pumpEventQueue();

      expect(
        catalog.queries.length - beforeTyping,
        1,
        reason: 'Rapid keystrokes must produce one request, not three.',
      );
      expect(catalog.queries.last.normalizedSearch, 'sofa');
      expect(controller.criteria.normalizedSearch, 'sofa');
    });

    test('submits immediately, bypassing the pending debounce', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(
        catalog,
        debounce: const Duration(milliseconds: 200),
      );
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();
      final before = catalog.queries.length;

      controller.onSearchTextChanged('table');
      await controller.submitSearch();

      expect(catalog.queries.length - before, 1);
      expect(controller.criteria.normalizedSearch, 'table');
    });

    test('never re-requests an identical normalised query', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();
      final before = catalog.queries.length;

      await controller.selectSort(CatalogSort.newest);

      expect(catalog.queries.length, before);
    });

    test('clears the search and keeps the other criteria', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();

      await controller.selectCategory('sofas');
      controller.onSearchTextChanged('sofa');
      await controller.submitSearch();
      await controller.clearSearch();

      expect(controller.searchText, isEmpty);
      expect(controller.criteria.normalizedSearch, isNull);
      expect(
        controller.criteria.categorySlug,
        'sofas',
        reason: 'Clearing text must not silently clear the category.',
      );
    });

    test(
      'a category change during a pending debounce issues one request',
      () async {
        final catalog = _RecordingCatalogRepository();
        final harness = _build(catalog);
        final controller = harness.controller;
        addTearDown(controller.dispose);
        await pumpEventQueue();
        final before = catalog.queries.length;

        controller.onSearchTextChanged('sofa');
        await controller.selectCategory('sofas');
        await Future<void>.delayed(controller.debounce * 2);

        expect(
          catalog.queries.length,
          before + 1,
          reason: 'The pending text must ride along with the category change.',
        );
        expect(controller.criteria.normalizedSearch, 'sofa');
        expect(controller.criteria.categorySlug, 'sofas');
      },
    );

    test(
      'a sort change during a pending debounce issues one request',
      () async {
        final catalog = _RecordingCatalogRepository();
        final harness = _build(catalog);
        final controller = harness.controller;
        addTearDown(controller.dispose);
        await pumpEventQueue();
        final before = catalog.queries.length;

        controller.onSearchTextChanged('sofa');
        await controller.selectSort(CatalogSort.priceLowToHigh);
        await Future<void>.delayed(controller.debounce * 2);

        expect(catalog.queries.length, before + 1);
        expect(controller.criteria.normalizedSearch, 'sofa');
        expect(controller.criteria.sort, CatalogSort.priceLowToHigh);
      },
    );

    test('changing a criterion resets pagination to the first page', () async {
      final catalog = _RecordingCatalogRepository(multiPage: true);
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();

      await controller.results.loadNextPage();
      expect(controller.results.state, isA<AsyncContent<CatalogPage>>());
      expect(
        (controller.results.state as AsyncContent<CatalogPage>)
            .data
            .pagination
            .currentPage,
        2,
      );

      await controller.selectSort(CatalogSort.priceLowToHigh);

      expect(catalog.queries.last.page, CatalogQuery.firstPage);
      expect(catalog.queries.last.sort, CatalogSort.priceLowToHigh);
    });

    test('preserves search text across a sort change', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();

      controller.onSearchTextChanged('sofa');
      await controller.submitSearch();
      await controller.selectSort(CatalogSort.nameAToZ);

      expect(controller.criteria.normalizedSearch, 'sofa');
      expect(controller.criteria.sort, CatalogSort.nameAToZ);
      expect(catalog.queries.last.normalizedSearch, 'sofa');
    });

    test('discards the previous result set after a criteria change', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();
      final previous = controller.results;

      await controller.selectCategory('sofas');

      expect(
        identical(controller.results, previous),
        isFalse,
        reason: 'A new result set needs its own controller.',
      );
      await pumpEventQueue();
      expect(controller.results.state, isA<AsyncContent<CatalogPage>>());
    });

    test('reset restores the unfiltered catalog without dropping the '
        'made-to-order restriction', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();

      controller.onSearchTextChanged('sofa');
      await controller.submitSearch();
      await controller.selectCategory('sofas');
      await controller.selectSort(CatalogSort.priceLowToHigh);
      await controller.reset();

      expect(controller.hasActiveCriteria, isFalse);
      expect(controller.searchText, isEmpty);
      expect(
        catalog.queries.last.toQueryParameters()['product_type'],
        'MADE_TO_ORDER',
        reason: 'A reset must never widen beyond made-to-order.',
      );
    });

    test('reports an empty result as empty, not as a failure', () async {
      final catalog = _RecordingCatalogRepository(products: const []);
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);

      await pumpEventQueue();

      expect(controller.results.state, isA<AsyncEmpty<CatalogPage>>());
    });

    test('surfaces a transport failure with a retryable state', () async {
      final catalog = _RecordingCatalogRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);

      await pumpEventQueue();

      expect(controller.results.state, isA<AsyncFailure<CatalogPage>>());
      expect(
        (controller.results.state as AsyncFailure<CatalogPage>).error.title,
        'Connection problem',
      );
    });

    test('retrying after a failure recovers', () async {
      final catalog = _RecordingCatalogRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final harness = _build(catalog);
      final controller = harness.controller;
      addTearDown(controller.dispose);
      await pumpEventQueue();

      catalog.error = null;
      await controller.results.load();
      await pumpEventQueue();

      expect(controller.results.state, isA<AsyncContent<CatalogPage>>());
    });

    test(
      'ignores a stale response that arrives after newer criteria',
      () async {
        final catalog = _RecordingCatalogRepository();
        catalog.deferFirstCall = true;
        final harness = _build(catalog);
        final controller = harness.controller;
        addTearDown(controller.dispose);

        final stale = controller.results;
        controller.onSearchTextChanged('sofa');
        await controller.submitSearch();
        await pumpEventQueue();

        catalog.gate.complete();
        await catalog.gate.future;
        await Future<void>.delayed(Duration.zero);
        await pumpEventQueue();

        expect(
          controller.criteria.normalizedSearch,
          'sofa',
          reason: 'Only the newest criteria may remain applied.',
        );
        expect(identical(controller.results, stale), isFalse);
        expect(stale.state, isA<AsyncLoading<CatalogPage>>());
      },
    );

    test('loads category options independently of the results', () async {
      final categories = _RecordingCategoryRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final controller = SearchController(
        catalogRepository: _RecordingCatalogRepository(),
        categoryRepository: categories,
      );
      addTearDown(controller.dispose);

      await pumpEventQueue();

      expect(
        controller.categories.state,
        isA<AsyncFailure<CategoryPage>>(),
        reason: 'A category failure must not take search down with it.',
      );
      expect(controller.results.state, isA<AsyncContent<CatalogPage>>());
    });

    test('stops requesting and notifies after disposal', () async {
      final catalog = _RecordingCatalogRepository();
      final harness = _build(catalog);
      final controller = harness.controller;
      await pumpEventQueue();
      final before = catalog.queries.length;

      controller.dispose();
      controller
        ..onSearchTextChanged('sofa')
        ..submitSearch();
      await controller.selectCategory('sofas');
      await Future<void>.delayed(const Duration(milliseconds: 20));
      await pumpEventQueue();

      expect(
        catalog.queries.length,
        before,
        reason: 'A disposed controller must not issue further requests.',
      );
    });
  });
}

final Matcher isUnfilteredPredicate = predicate<CatalogQuery>(
  (query) => query.isUnfiltered,
  'is an unfiltered CatalogQuery',
);

/// Builds a controller over recording repositories and exposes the category
/// repository so tests can assert on it.
({SearchController controller, _RecordingCategoryRepository categories}) _build(
  _RecordingCatalogRepository catalog, {
  Duration debounce = SearchController.defaultDebounce,
}) {
  final categories = _RecordingCategoryRepository();
  return (
    categories: categories,
    controller: SearchController(
      catalogRepository: catalog,
      categoryRepository: categories,
      debounce: debounce,
    ),
  );
}

class _RecordingCatalogRepository implements CatalogRepository {
  _RecordingCatalogRepository({
    List<ProductSummary>? products,
    this.error,
    this.multiPage = false,
  }) : products = products ?? <ProductSummary>[_product('lounge-chair')];

  final List<ProductSummary> products;
  Object? error;
  final bool multiPage;
  final List<CatalogQuery> queries = <CatalogQuery>[];
  final Completer<void> gate = Completer<void>();
  int? _deferredCallIndex;

  set deferFirstCall(bool value) {
    _deferredCallIndex = value ? queries.length + 1 : null;
  }

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    queries.add(query);
    final callIndex = queries.length;
    if (_deferredCallIndex == callIndex) {
      _deferredCallIndex = null;
      await gate.future;
    }
    if (error != null) throw error!;
    if (products.isEmpty) {
      return const CatalogPage(
        products: <ProductSummary>[],
        pagination: ApiPaginationFixture.empty,
      );
    }
    final isLast = !multiPage || query.page > 1;
    final pageProducts = isLast
        ? products
        : <ProductSummary>[products.first, _product('second')];
    return CatalogPage(
      products: pageProducts,
      pagination: ApiPaginationFixture.of(
        currentPage: query.page,
        total: multiPage ? 3 : products.length,
        lastPage: multiPage ? 2 : 1,
      ),
    );
  }
}

extension ApiPaginationFixture on ApiPagination {
  static const ApiPagination empty = ApiPagination(
    currentPage: 1,
    perPage: 20,
    total: 0,
    lastPage: 1,
    hasNext: false,
    hasPrevious: false,
  );

  static ApiPagination of({
    required int currentPage,
    required int total,
    required int lastPage,
  }) => ApiPagination(
    currentPage: currentPage,
    perPage: 20,
    total: total,
    lastPage: lastPage,
    hasNext: currentPage < lastPage,
    hasPrevious: currentPage > 1,
  );
}

class _RecordingCategoryRepository implements CategoryRepository {
  _RecordingCategoryRepository({this.error});

  Object? error;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    if (error != null) throw error!;
    return const CategoryPage(
      categories: <CategorySummary>[
        CategorySummary(id: 'cat_sofas', name: 'Sofas', slug: 'sofas'),
      ],
      pagination: ApiPaginationFixture.empty,
    );
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) => throw UnimplementedError();
}

ProductSummary _product(String slug) => ProductSummary(
  id: 'prod_$slug',
  slug: slug,
  name: slug.replaceAll('-', ' '),
  productType: 'MADE_TO_ORDER',
  price: const Money(amount: 10000000, currency: 'TZS'),
  category: const ProductCategorySummary(
    id: 'cat_sofas',
    slug: 'sofas',
    name: 'Sofas',
  ),
  primaryImage: null,
  availability: 'available',
  stockIndicator: 'MADE_TO_ORDER',
);
