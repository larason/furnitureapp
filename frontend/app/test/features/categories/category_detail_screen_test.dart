import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/categories/presentation/category_detail_screen.dart';
import 'package:sl_furnitures/features/categories/presentation/widgets/category_header.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('shows a loading state while the category is pending', (
    tester,
  ) async {
    final repository = _StubCategoryRepository(pending: true);

    await tester.pumpWidget(_app(repository: repository));
    await tester.pump();

    expect(find.text('Loading category'), findsOneWidget);
    expect(tester.takeException(), isNull);
    repository.gate.complete();
  });

  testWidgets('renders category identity, description, and products', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(
          detail: _detail(description: 'Sofas, tables, and accent seating.'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Living Room'), findsWidgets);
    expect(find.text('Sofas, tables, and accent seating.'), findsOneWidget);
    expect(find.text('Made to order'), findsWidgets);
    expect(find.byType(CategoryHeader), findsOneWidget);
    expect(find.text('Lounge chair'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('omits the description when the API returns none', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(repository: _StubCategoryRepository(detail: _detail())),
    );
    await tester.pumpAndSettle();

    expect(find.byType(CategoryHeader), findsOneWidget);
    expect(find.text('Made to order'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reports a missing category as unavailable, not as empty', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(error: _notFound()),
        catalog: _StubCatalogRepository(products: const []),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('This category is unavailable'), findsOneWidget);
    expect(
      find.text('No made-to-order furniture here yet'),
      findsNothing,
      reason: 'A failed category request must never look like an empty room.',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('returns to the categories index from an unavailable category', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(error: _notFound()),
        catalog: _StubCatalogRepository(products: const []),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Browse categories'));
    await tester.pumpAndSettle();

    expect(find.text('categories-index'), findsOneWidget);
  });

  testWidgets('shows a category-specific empty state with no products', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(),
        catalog: _StubCatalogRepository(products: const []),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('No made-to-order furniture here yet'), findsOneWidget);
    expect(
      find.text('This category is unavailable'),
      findsNothing,
      reason: 'An empty listing must not imply the category is missing.',
    );
    expect(find.text('Living Room'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('keeps the category when the product listing fails', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(),
        catalog: _StubCatalogRepository(
          error: const ApiTransportException(
            kind: ApiTransportFailureKind.connection,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Living Room'), findsWidgets);
    expect(find.text('Connection problem'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('falls back when the category image cannot be decoded', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(
          detail: CategoryDetail(
            id: 'cat_living',
            name: 'Living Room',
            slug: 'living-room',
            image: CategoryImage(
              url: '',
              assetPath: 'assets/furnitures/fixtures/categories/missing.jpg',
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Living Room'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reflows the landing page at 2x text scaling', (tester) async {
    await tester.pumpWidget(
      _app(
        repository: _StubCategoryRepository(
          detail: CategoryDetail(
            id: 'cat_office',
            name: 'Home Office & Corporate Workspaces',
            slug: 'home-office-corporate-workspaces',
            description: 'Desks, chairs, and storage for shared workspaces.',
          ),
        ),
        textScale: 2,
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Home Office & Corporate Workspaces'),
      findsWidgets,
      reason: 'The category name must render at every supported text scale.',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('exposes the category name as the heading and image label', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(
      _app(repository: _StubCategoryRepository(detail: _detail(image: true))),
    );
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel('Furniture by room'), findsNothing);
    expect(find.bySemanticsLabel('Living Room'), findsWidgets);
    expect(find.text('Made to order'), findsWidgets);
    handle.dispose();
  });

  testWidgets('rebuilds when the route resolves a different category', (
    tester,
  ) async {
    final repository = _StubCategoryRepository(detail: _detail());
    final catalog = _StubCatalogRepository();
    final router = _router(repository, catalog);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();
    expect(find.text('Lounge chair'), findsOneWidget);

    repository.detail = CategoryDetail(
      id: 'cat_bedroom',
      name: 'Bedroom',
      slug: 'bedroom',
    );
    catalog.reset();
    router.go(AppRoutes.category('bedroom'));
    await tester.pumpAndSettle();

    expect(find.text('Bedroom'), findsWidgets);
    expect(
      find.text('Lounge chair'),
      findsNothing,
      reason: 'Products from the previous category must not survive.',
    );
    expect(catalog.requestedSlugs, <String?>['bedroom']);
    router.dispose();
  });
}

CategoryDetail _detail({String? description, bool image = false}) =>
    CategoryDetail(
      id: 'cat_living',
      name: 'Living Room',
      slug: 'living-room',
      description: description,
      image: image ? const CategoryImage(url: '') : null,
    );

ApiError _notFound() => ApiError(
  statusCode: 404,
  errors: const <ApiErrorItem>[
    ApiErrorItem(
      code: 'RESOURCE_NOT_FOUND',
      message: 'The requested category was not found.',
    ),
  ],
);

ProductSummary _product(String id, {String categorySlug = 'living-room'}) =>
    ProductSummary(
      id: id,
      slug: 'fixture-$id',
      name: id,
      productType: 'MADE_TO_ORDER',
      price: const Money(amount: 10000000, currency: 'TZS'),
      category: ProductCategorySummary(
        id: 'cat_living',
        slug: categorySlug,
        name: 'Living Room',
      ),
      primaryImage: null,
      availability: 'available',
      stockIndicator: 'MADE_TO_ORDER',
    );

Widget _app({
  required CategoryRepository repository,
  CatalogRepository? catalog,
  double textScale = 1,
}) {
  final widget = MaterialApp.router(
    theme: AppTheme.light(),
    routerConfig: _router(repository, catalog ?? _StubCatalogRepository()),
    builder: textScale == 1
        ? null
        : (context, child) => MediaQuery(
            data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
            child: child!,
          ),
  );
  return widget;
}

GoRouter _router(CategoryRepository repository, CatalogRepository catalog) =>
    GoRouter(
      initialLocation: AppRoutes.category('living-room'),
      routes: <RouteBase>[
        GoRoute(
          path: AppRoutes.categories,
          name: AppRoutes.categoriesName,
          builder: (_, _) => const Scaffold(body: Text('categories-index')),
          routes: <RouteBase>[
            GoRoute(
              path: AppRoutes.categoryPath,
              name: AppRoutes.categoryName,
              builder: (_, state) => CategoryDetailScreen(
                identifier: state.pathParameters['categoryId']!,
                categoryRepository: repository,
                catalogRepository: catalog,
              ),
            ),
          ],
        ),
      ],
    );

class _StubCategoryRepository implements CategoryRepository {
  _StubCategoryRepository({
    CategoryDetail? detail,
    this.error,
    this.pending = false,
  }) : detail =
           detail ??
           CategoryDetail(
             id: 'cat_living',
             name: 'Living Room',
             slug: 'living-room',
           );

  CategoryDetail detail = CategoryDetail(
    id: 'cat_living',
    name: 'Living Room',
    slug: 'living-room',
  );
  final Object? error;
  final bool pending;
  final Completer<void> gate = Completer<void>();
  int calls = 0;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async => CategoryPage(
    categories: <CategorySummary>[detail.summary],
    pagination: const ApiPagination(
      currentPage: 1,
      perPage: 20,
      total: 1,
      lastPage: 1,
      hasNext: false,
      hasPrevious: false,
    ),
  );

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async {
    calls++;
    if (pending) await gate.future;
    if (error != null) throw error!;
    return detail;
  }
}

class _StubCatalogRepository implements CatalogRepository {
  _StubCatalogRepository({List<ProductSummary>? products, this.error})
    : products = products ?? <ProductSummary>[_product('Lounge chair')];

  final List<ProductSummary> products;
  final Object? error;
  final List<String?> requestedSlugs = <String?>[];

  void reset() => requestedSlugs.clear();

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    requestedSlugs.add(query.categorySlug);
    if (error != null) throw error!;
    final visible = query.categorySlug == null
        ? products
        : products
              .where((product) => product.category.slug == query.categorySlug)
              .toList();
    return CatalogPage(
      products: visible,
      pagination: ApiPagination(
        currentPage: query.page,
        perPage: 20,
        total: visible.length,
        lastPage: 1,
        hasNext: false,
        hasPrevious: query.page > 1,
      ),
    );
  }
}
