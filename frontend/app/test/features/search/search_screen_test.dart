import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/catalog/presentation/product_card.dart';
import 'package:sl_furnitures/features/catalog/presentation/product_grid.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/search/presentation/search_screen_host.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('shows made-to-order furniture before any search', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.text('Find your furniture'), findsOneWidget);
    expect(find.text('Lounge chair'), findsOneWidget);
    expect(find.text('Showing all made-to-order furniture.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('exposes a labelled search field with a search action', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    final field = find.byKey(const ValueKey<String>('search.text_field'));
    expect(field, findsOneWidget);
    expect(
      tester.widget<TextField>(field).decoration?.labelText,
      'Search furniture',
    );
    expect(
      tester.widget<TextField>(field).textInputAction,
      TextInputAction.search,
    );
    expect(
      tester.widget<TextField>(field).maxLength,
      100,
      reason: 'The contract caps search at 100 characters.',
    );
    expect(find.text('Search furniture'), findsWidgets);
  });

  testWidgets('keeps the submit icon readable on the primary action surface', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    final button = tester.widget<IconButton>(
      find.byKey(const ValueKey<String>('search.submit_button')),
    );
    final colors = AppTheme.light().colorScheme;

    expect(
      button.style?.foregroundColor?.resolve(<WidgetState>{}),
      colors.onPrimary,
    );
    expect(
      button.style?.backgroundColor?.resolve(<WidgetState>{}),
      colors.primary,
    );
  });

  testWidgets('searches for typed text and updates the results', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey<String>('search.text_field')),
      'sofa',
    );
    await tester.pumpAndSettle(const Duration(milliseconds: 400));

    expect(find.text('Soft two-seat sofa'), findsOneWidget);
    expect(
      find.text('Lounge chair'),
      findsNothing,
      reason: 'A non-matching product must leave the result set.',
    );
    expect(
      find.textContaining('Showing furniture matching "sofa"'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('clears the search from the field control', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey<String>('search.text_field')),
      'sofa',
    );
    await tester.pumpAndSettle(const Duration(milliseconds: 400));
    expect(
      find.byKey(const ValueKey<String>('search.clear_button')),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const ValueKey<String>('search.clear_button')));
    await tester.pumpAndSettle(const Duration(milliseconds: 400));

    expect(find.text('Lounge chair'), findsOneWidget);
    expect(find.text('Showing all made-to-order furniture.'), findsOneWidget);
  });

  testWidgets('filters by a server-returned category', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.tap(
      find.byKey(const ValueKey<String>('search.category_filter')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sofas').last);
    await tester.pumpAndSettle();

    expect(find.textContaining('in Sofas'), findsOneWidget);
    expect(catalogQueries.last.categorySlug, 'sofas');
  });

  testWidgets('changes the sort order through the contract mapping', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey<String>('search.sort_filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Price: High to Low').last);
    await tester.pumpAndSettle();

    expect(catalogQueries.last.sort.sortField, 'price');
    expect(catalogQueries.last.sort.direction, 'desc');
    expect(find.textContaining('sorted by price: high to low'), findsOneWidget);
  });

  testWidgets('combines search, category, and sort in one request', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey<String>('search.text_field')),
      'sofa',
    );
    await tester.pumpAndSettle(const Duration(milliseconds: 400));
    await tester.tap(find.byKey(const ValueKey<String>('search.sort_filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Price: High to Low').last);
    await tester.pumpAndSettle();

    final last = catalogQueries.last;
    expect(last.normalizedSearch, 'sofa');
    expect(last.sort.sortField, 'price');
    expect(last.sort.direction, 'desc');
    expect(last.toQueryParameters()['product_type'], 'MADE_TO_ORDER');
  });

  testWidgets('resets every criterion back to the catalog', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey<String>('search.text_field')),
      'sofa',
    );
    await tester.pumpAndSettle(const Duration(milliseconds: 400));
    await tester.tap(find.byKey(const ValueKey<String>('search.reset_button')));
    await tester.pumpAndSettle(const Duration(milliseconds: 400));

    expect(find.text('Showing all made-to-order furniture.'), findsOneWidget);
    expect(find.text('Lounge chair'), findsOneWidget);
    expect(catalogQueries.last.isUnfiltered, isTrue);
  });

  testWidgets('shows the backend result total, not the loaded page length', (
    tester,
  ) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(
      find.text('$catalogPageCount pieces'),
      findsOneWidget,
      reason: 'Counting loaded rows would misreport a paginated catalog.',
    );
  });

  testWidgets('reports no matches with a working reset', (tester) async {
    await tester.pumpWidget(_app(emptyResults: true));
    await tester.pumpAndSettle();

    expect(find.text('No furniture matches your search'), findsOneWidget);

    await tester.tap(find.text('Reset filters'));
    await tester.pumpAndSettle();

    expect(
      find.text('No furniture matches your search'),
      findsOneWidget,
      reason: 'This empty stub remains empty after reset.',
    );
  });

  testWidgets('shows a retryable error for a failed search', (tester) async {
    await tester.pumpWidget(
      _app(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Connection problem'), findsOneWidget);

    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('Lounge chair'), findsOneWidget);
  });

  testWidgets('reuses the shared product card and grid', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.byType(ProductCard), findsWidgets);
    expect(find.byType(ProductGridSliver), findsOneWidget);
  });

  testWidgets('opens product detail from a search result', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    final card = find.byKey(const ValueKey<String>('prod_lounge-chair'));
    tester.widget<ProductCard>(card).onTap();
    await tester.pumpAndSettle();
    await tester.pumpAndSettle();

    expect(find.text('Product detail for lounge-chair'), findsOneWidget);
  });

  testWidgets('shows a loading state while results are pending', (
    tester,
  ) async {
    final router = _router(
      _StubCatalogRepository(pending: true),
      _StubCategoryRepository(),
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pump();

    expect(find.text('Loading furniture'), findsOneWidget);
    router.go(AppRoutes.search);
    await tester.pump(const Duration(milliseconds: 500));
  });

  testWidgets('reflows at 2x text scaling without clipping', (tester) async {
    await tester.pumpWidget(_app(textScale: 2));
    await tester.pumpAndSettle();

    expect(find.text('Find your furniture'), findsOneWidget);
    expect(find.text('Newest'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reflows on a narrow screen', (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(
      find.byKey(const ValueKey<String>('search.category_filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const ValueKey<String>('search.sort_filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('announces the search field, criteria, and result count', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel(RegExp('Search furniture')), findsWidgets);
    expect(
      find.bySemanticsLabel('Showing all made-to-order furniture.'),
      findsOneWidget,
    );
    expect(find.bySemanticsLabel('Reset'), findsOneWidget);
    handle.dispose();
  });

  testWidgets('offers no cart, checkout, or payment control', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pumpAndSettle();

    for (final forbidden in <String>[
      'Add to cart',
      'Buy now',
      'Checkout',
      'Pay',
      'Reserve',
      'Favourite',
    ]) {
      expect(find.text(forbidden), findsNothing, reason: 'Found "$forbidden".');
    }
  });

  testWidgets('stays usable when category options fail to load', (
    tester,
  ) async {
    final router = _router(
      _StubCatalogRepository(),
      _StubCategoryRepository(
        error: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      ),
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();

    expect(find.text('Lounge chair'), findsOneWidget);
    expect(
      find.byKey(const ValueKey<String>('search.category_filter')),
      findsOneWidget,
    );
    expect(find.text('All categories'), findsOneWidget);
  });

  testWidgets('stops a pending debounce when the route is left', (
    tester,
  ) async {
    final catalog = _StubCatalogRepository();
    final router = _router(catalog, _StubCategoryRepository());
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey<String>('search.text_field')),
      'sofa',
    );
    await tester.pump(const Duration(milliseconds: 10));
    router.go(AppRoutes.home);
    await tester.pumpAndSettle(const Duration(milliseconds: 400));

    expect(tester.takeException(), isNull);
  });
}

const int catalogPageCount = 3;

List<CatalogQuery> catalogQueries = <CatalogQuery>[];

Widget _app({bool emptyResults = false, Object? error, double textScale = 1}) {
  catalogQueries = <CatalogQuery>[];
  final catalog = _StubCatalogRepository(
    emptyResults: emptyResults,
    error: error,
    queries: catalogQueries,
  );
  final app = MaterialApp.router(
    theme: AppTheme.light(),
    routerConfig: _router(catalog, _StubCategoryRepository()),
  );
  if (textScale == 1) return app;
  return Builder(
    builder: (context) => MediaQuery(
      data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
      child: app,
    ),
  );
}

GoRouter _router(CatalogRepository catalog, CategoryRepository categories) =>
    GoRouter(
      initialLocation: AppRoutes.search,
      routes: <RouteBase>[
        GoRoute(
          path: AppRoutes.home,
          name: AppRoutes.homeName,
          builder: (_, _) => const Scaffold(body: Text('home')),
        ),
        GoRoute(
          path: AppRoutes.products,
          name: AppRoutes.productsName,
          builder: (_, _) => const Scaffold(body: Text('catalog')),
        ),
        GoRoute(
          path: '${AppRoutes.products}/${AppRoutes.productPath}',
          name: AppRoutes.productName,
          builder: (_, state) => Scaffold(
            body: Text(
              'Product detail for ${state.pathParameters['productId']}',
            ),
          ),
        ),
        GoRoute(
          path: AppRoutes.search,
          name: AppRoutes.searchName,
          builder: (_, _) => SearchScreenHost(
            catalogRepository: catalog,
            categoryRepository: categories,
          ),
        ),
      ],
    );

class _StubCatalogRepository implements CatalogRepository {
  _StubCatalogRepository({
    this.emptyResults = false,
    this.error,
    this.pending = false,
    List<CatalogQuery>? queries,
  }) : queries = queries ?? <CatalogQuery>[];

  final bool emptyResults;
  Object? error;
  final bool pending;
  final List<CatalogQuery> queries;
  final Completer<void> gate = Completer<void>();

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    queries.add(query);
    if (pending) await gate.future;
    final requestError = error;
    error = null;
    if (requestError != null) throw requestError;
    final allProducts = emptyResults
        ? const <ProductSummary>[]
        : <ProductSummary>[
            _product('Lounge chair', 'lounge-chair', 10000000),
            _product('Soft two-seat sofa', 'soft-two-seat-sofa', 24500000),
            _product('Barrel chair', 'barrel-chair', 10000000),
          ];
    final search = query.normalizedSearch?.toLowerCase();
    final products = allProducts
        .where((product) {
          final matchesSearch =
              search == null ||
              product.name.toLowerCase().contains(search) ||
              product.slug.contains(search);
          final matchesCategory =
              query.categorySlug == null ||
              product.category.slug == query.categorySlug;
          return matchesSearch && matchesCategory;
        })
        .toList(growable: false);
    return CatalogPage(
      products: products,
      pagination: ApiPagination(
        currentPage: 1,
        perPage: 20,
        total: products.length,
        lastPage: 1,
        hasNext: false,
        hasPrevious: false,
      ),
    );
  }
}

class _StubCategoryRepository implements CategoryRepository {
  _StubCategoryRepository({this.error});

  final Object? error;

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
      pagination: ApiPagination(
        currentPage: 1,
        perPage: 20,
        total: 1,
        lastPage: 1,
        hasNext: false,
        hasPrevious: false,
      ),
    );
  }

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) => throw UnimplementedError();
}

ProductSummary _product(String name, String slug, int amount) => ProductSummary(
  id: 'prod_$slug',
  slug: slug,
  name: name,
  productType: 'MADE_TO_ORDER',
  price: Money(amount: amount, currency: 'TZS'),
  category: const ProductCategorySummary(
    id: 'cat_sofas',
    slug: 'sofas',
    name: 'Sofas',
  ),
  primaryImage: null,
  availability: 'available',
  stockIndicator: 'MADE_TO_ORDER',
);
