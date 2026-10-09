import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/core/presentation/async_view_state.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/catalog/presentation/catalog_controller.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/categories/presentation/categories_controller.dart';
import 'package:sl_furnitures/features/categories/presentation/category_detail_controller.dart';

void main() {
  group('CAT-001 category product query', () {
    test('uses the canonical category query parameter', () async {
      final transport = _FakeTransport(_products(<Object?>[]));
      final repository = ApiCatalogRepository(_client(transport));

      await repository.fetchProducts(
        const CatalogQuery(categorySlug: 'living-room'),
      );

      expect(transport.lastRequest!.uri.path, '/api/v1/products');
      expect(transport.lastRequest!.uri.queryParameters, <String, String>{
        'category': 'living-room',
        'sort': 'created_at',
        'sort_direction': 'desc',
        'product_type': 'MADE_TO_ORDER',
        'page': '1',
        'per_page': '20',
      });
    });

    test('always filters to made-to-order furniture', () async {
      final transport = _FakeTransport(_products(<Object?>[]));
      final repository = ApiCatalogRepository(_client(transport));

      await repository.fetchProducts(const CatalogQuery(page: 2));

      expect(
        transport.lastRequest!.uri.queryParameters['product_type'],
        'MADE_TO_ORDER',
      );
      expect(
        transport.lastRequest!.uri.queryParameters.containsKey('category'),
        isFalse,
      );
    });

    test(
      'never calls the rejected nested category-products endpoint',
      () async {
        final transport = _FakeTransport(_products(<Object?>[]));
        final repository = ApiCatalogRepository(_client(transport));

        await repository.fetchProducts(
          const CatalogQuery(categorySlug: 'living-room'),
        );

        expect(transport.lastRequest!.uri.path, '/api/v1/products');
        expect(
          transport.lastRequest!.uri.path,
          isNot(contains('/categories')),
          reason:
              'The rejected /categories/{category}/products route must never '
              'be requested; the category is a query parameter instead.',
        );
      },
    );

    test('preserves server pagination for category pages', () async {
      final transport = _FakeTransport(
        _products(<Object?>[], page: 1, total: 25, lastPage: 2),
      );
      final repository = ApiCatalogRepository(_client(transport));

      final page = await repository.fetchProducts(
        const CatalogQuery(categorySlug: 'living-room'),
      );

      expect(page.pagination.total, 25);
      expect(page.pagination.lastPage, 2);
      expect(page.pagination.hasNext, isTrue);
    });
  });

  group('category detail product integration', () {
    test('scopes the listing to the canonical slug from CAT-004', () async {
      final catalog = _StubCatalogRepository();
      final controller = CategoryDetailController(
        categoryRepository: _DetailCategoryRepository(
          detail: _detail(slug: 'living-room'),
        ),
        catalogRepository: catalog,
        identifier: 'cat_living',
      );
      addTearDown(controller.dispose);

      await controller.load();
      await pumpEventQueue();

      expect(controller.products, isA<CatalogController>());
      expect(controller.products!.criteria.categorySlug, 'living-room');
      expect(catalog.lastCategorySlug, 'living-room');
      expect(catalog.calls, 1);
    });

    test('reports an empty product listing as empty, not as missing', () async {
      final controller = CategoryDetailController(
        categoryRepository: _DetailCategoryRepository(
          detail: _detail(slug: 'bedroom'),
        ),
        catalogRepository: _StubCatalogRepository(products: const []),
        identifier: 'bedroom',
      );
      addTearDown(controller.dispose);

      await controller.load();
      await pumpEventQueue();

      expect(controller.categoryUnavailable, isFalse);
      expect(controller.state, isA<AsyncContent<CategoryDetail>>());
      expect(controller.products!.state, isA<AsyncEmpty<CatalogPage>>());
    });

    test('keeps loaded products when pagination fails', () async {
      final catalog = _StubCatalogRepository(
        products: [_product('first'), _product('second')],
        failFromPage: 2,
      );
      final controller = CategoryDetailController(
        categoryRepository: _DetailCategoryRepository(
          detail: _detail(slug: 'living-room'),
        ),
        catalogRepository: catalog,
        identifier: 'living-room',
      );
      addTearDown(controller.dispose);

      await controller.load();
      await pumpEventQueue();
      await controller.products!.loadNextPage();

      final state = controller.products!.state;
      expect(state, isA<AsyncContent<CatalogPage>>());
      expect(
        (state as AsyncContent<CatalogPage>).data.products.map((p) => p.id),
        <String>['first', 'second'],
      );
      expect(controller.products!.nextPageError, isNotNull);
    });

    test('reloads products when the resolved category changes', () async {
      final catalog = _StubCatalogRepository();
      final repository = _DetailCategoryRepository(
        detail: _detail(slug: 'living-room'),
      );
      final controller = CategoryDetailController(
        categoryRepository: repository,
        catalogRepository: catalog,
        identifier: 'living-room',
      );
      addTearDown(controller.dispose);

      await controller.load();
      await pumpEventQueue();
      expect(controller.products!.criteria.categorySlug, 'living-room');

      repository.detail = _detail(slug: 'bedroom');
      await controller.refresh();
      await pumpEventQueue();

      expect(controller.products!.criteria.categorySlug, 'bedroom');
      expect(catalog.lastCategorySlug, 'bedroom');
      expect(catalog.calls, 2);
    });

    test('discards a stale collection page after a refresh', () async {
      final repository = _PagedCategoryRepository();
      final controller = CategoriesController(repository);
      addTearDown(controller.dispose);

      await controller.load();
      final nextPage = controller.loadNextPage();
      await Future<void>.delayed(Duration.zero);
      await controller.refresh();

      repository.pendingPage.complete(_page('stale', idPrefix: 'stale'));
      await nextPage;

      final state = controller.state;
      expect(state, isA<AsyncContent<CategoryPage>>());
      expect(
        (state as AsyncContent<CategoryPage>).data.categories.single.id,
        'category-refreshed',
      );
    });

    test('ignores a duplicate page request while one is in flight', () async {
      final repository = _PagedCategoryRepository();
      final controller = CategoriesController(repository);
      addTearDown(controller.dispose);

      await controller.load();
      final first = controller.loadNextPage();
      final second = controller.loadNextPage();
      repository.pendingPage.complete(_page('next'));
      await first;
      await second;

      expect(repository.pageRequests, 2);
    });

    test('yields pagination to an in-flight refresh', () async {
      final repository = _RefreshPendingCategoryRepository();
      final controller = CategoriesController(repository);
      addTearDown(controller.dispose);

      await controller.load();
      final refresh = controller.refresh();
      await Future<void>.delayed(Duration.zero);
      expect(controller.state, isA<AsyncRefreshing<CategoryPage>>());

      await controller.loadNextPage();

      expect(
        repository.pageRequests,
        0,
        reason:
            'A pending refresh must not be cancelled by a page request, so no '
            'additional page may be requested.',
      );

      repository.pendingRefresh.complete(
        _page('fresh', idPrefix: 'category', hasNext: true),
      );
      await refresh;

      final state = controller.state;
      expect(state, isA<AsyncContent<CategoryPage>>());
      expect(
        (state as AsyncContent<CategoryPage>).data.categories.single.id,
        'category-fresh',
      );
    });
  });
}

CategoryDetail _detail({required String slug}) => CategoryDetail(
  id: 'cat_${slug.replaceAll('-', '_')}',
  name: slug,
  slug: slug,
);

ProductSummary _product(String id) => ProductSummary(
  id: id,
  slug: id,
  name: id,
  productType: 'MADE_TO_ORDER',
  price: const Money(amount: 10000000, currency: 'TZS'),
  category: const ProductCategorySummary(
    id: 'cat_living',
    slug: 'living-room',
    name: 'Living Room',
  ),
  primaryImage: null,
  availability: 'available',
  stockIndicator: 'MADE_TO_ORDER',
);

Map<String, Object?> _products(
  List<Object?> data, {
  int page = 1,
  int total = 0,
  int lastPage = 1,
}) => <String, Object?>{
  'data': data,
  'meta': <String, Object?>{
    'pagination': <String, Object?>{
      'current_page': page,
      'per_page': 20,
      'total': total,
      'last_page': lastPage,
      'has_next': page < lastPage,
      'has_previous': page > 1,
    },
  },
};

CategoryPage _page(
  String id, {
  String idPrefix = 'refreshed',
  bool hasNext = false,
}) => CategoryPage(
  categories: <CategorySummary>[
    CategorySummary(id: '$idPrefix-$id', name: id, slug: id),
  ],
  pagination: ApiPagination(
    currentPage: 1,
    perPage: 20,
    total: hasNext ? 2 : 1,
    lastPage: hasNext ? 2 : 1,
    hasNext: hasNext,
    hasPrevious: false,
  ),
);

ApiClient _client(_FakeTransport transport) => ApiClient(
  config: const AppConfig(
    environment: AppEnvironment.local,
    apiBaseUrl: 'http://127.0.0.1:8000',
  ),
  transport: transport,
);

class _StubCatalogRepository implements CatalogRepository {
  _StubCatalogRepository({List<ProductSummary>? products, this.failFromPage})
    : products = products ?? [_product('first')];

  final List<ProductSummary> products;
  final int? failFromPage;
  String? lastCategorySlug;
  int calls = 0;

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    calls++;
    lastCategorySlug = query.categorySlug;
    if (failFromPage != null && query.page >= failFromPage!) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.connection,
      );
    }
    final visible = query.categorySlug == null
        ? products
        : products.where((p) => p.category.slug == query.categorySlug).toList();
    return CatalogPage(
      products: query.page == 1 ? visible : const [],
      pagination: ApiPagination(
        currentPage: query.page,
        perPage: 20,
        total: visible.length,
        lastPage: 2,
        hasNext: query.page == 1,
        hasPrevious: query.page > 1,
      ),
    );
  }
}

class _DetailCategoryRepository implements CategoryRepository {
  _DetailCategoryRepository({required this.detail});

  CategoryDetail detail;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async => _page('all');

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async => detail;
}

class _PagedCategoryRepository implements CategoryRepository {
  final Completer<CategoryPage> pendingPage = Completer<CategoryPage>();
  int pageRequests = 1;
  int _calls = 0;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) {
    if (page > 1) {
      pageRequests++;
      return pendingPage.future;
    }
    _calls++;
    return Future.value(
      _page(
        _calls == 1 ? 'initial' : 'refreshed',
        idPrefix: 'category',
        hasNext: true,
      ),
    );
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async => _detail(slug: category);
}

/// Serves the first page immediately, then defers the next first-page request so
/// a refresh stays in flight while pagination is attempted.
class _RefreshPendingCategoryRepository implements CategoryRepository {
  final Completer<CategoryPage> pendingRefresh = Completer<CategoryPage>();
  int pageRequests = 0;
  int _calls = 0;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) {
    if (page > 1) {
      pageRequests++;
      return pendingRefresh.future;
    }
    _calls++;
    if (_calls == 1) {
      return Future.value(
        _page('initial', idPrefix: 'category', hasNext: true),
      );
    }
    return pendingRefresh.future;
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async => _detail(slug: category);
}

class _FakeTransport implements ApiTransport {
  _FakeTransport(this.payload);

  final Map<String, Object?> payload;
  ApiTransportRequest? lastRequest;

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest request) async {
    lastRequest = request;
    return ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: Uint8List.fromList(utf8.encode(jsonEncode(payload))),
    );
  }

  @override
  Future<void> close() async {}
}
