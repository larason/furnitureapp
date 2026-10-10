import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/categories/presentation/categories_screen.dart';
import 'package:sl_furnitures/features/categories/presentation/widgets/category_tile.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('shows a loading state while the collection is pending', (
    tester,
  ) async {
    final repository = _FakeCategoryRepository(pending: true);
    await tester.pumpWidget(_app(repository));
    await tester.pump();

    expect(find.text('Loading categories'), findsOneWidget);
    expect(tester.takeException(), isNull);
    repository.gate.complete();
  });

  testWidgets('renders the collection with an editorial heading', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _FakeCategoryRepository(
          categories: [_category('living-room', 'Living Room')],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Furniture by room'), findsOneWidget);
    expect(find.text('Living Room'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('renders a successful empty state', (tester) async {
    await tester.pumpWidget(_app(_FakeCategoryRepository()));
    await tester.pumpAndSettle();

    expect(find.text('No categories are published yet'), findsOneWidget);
    expect(find.text('Categories'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('shows a recoverable error with an explicit retry', (
    tester,
  ) async {
    final repository = _FakeCategoryRepository(
      error: const ApiTransportException(
        kind: ApiTransportFailureKind.connection,
      ),
      retryWith: [_category('living-room', 'Living Room')],
    );
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    expect(find.text('Connection problem'), findsOneWidget);

    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('Living Room'), findsWidgets);
    expect(repository.calls, 2);
  });

  testWidgets('navigates to the category detail route on tap', (tester) async {
    final router = _router(
      _FakeCategoryRepository(
        categories: [_category('living-room', 'Living Room')],
      ),
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(_routerApp(router));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Living Room'));
    await tester.pumpAndSettle();

    expect(
      find.text('detail:living-room'),
      findsOneWidget,
      reason: 'The tile must open the canonical category route.',
    );
  });

  testWidgets('falls back gracefully when a category image is missing', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _FakeCategoryRepository(
          categories: [
            _category('living-room', 'Living Room'),
            _category('bedroom', 'Bedroom'),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bedroom'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('falls back when a bundled fixture asset cannot be decoded', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _FakeCategoryRepository(
          categories: [
            _category(
              'living-room',
              'Living Room',
              assetPath: 'assets/furnitures/fixtures/categories/missing.jpg',
            ),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Living Room'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reflows without clipping at 2x text scaling', (tester) async {
    await tester.pumpWidget(
      _scaledApp(
        _FakeCategoryRepository(
          categories: [
            _category(
              'home-office-corporate-workspaces',
              'Home Office & Corporate Workspaces',
            ),
            _category('living-room', 'Living Room'),
          ],
        ),
        2,
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Home Office & Corporate Workspaces'),
      findsOneWidget,
      reason: 'The name must render at every supported text scale.',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('uses large single-column tiles on a narrow screen', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(
        _FakeCategoryRepository(
          categories: [
            _category('living-room', 'Living Room'),
            _category('bedroom', 'Bedroom'),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    final screenWidth = tester.getSize(find.byType(CategoriesScreen)).width;
    final tile = tester.getSize(find.byType(CategoryTile).first);
    expect(tile.width, greaterThan(screenWidth * 0.8));
    expect(tester.takeException(), isNull);
  });

  testWidgets('exposes one accessible label per category control', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(
      _app(
        _FakeCategoryRepository(
          categories: [_category('living-room', 'Living Room')],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.bySemanticsLabel('Living Room, browse furniture by category'),
      findsOneWidget,
    );
    expect(find.bySemanticsLabel('Furniture by room'), findsOneWidget);
    handle.dispose();
  });

  testWidgets('activates a category control from its single label', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    final router = _router(
      _FakeCategoryRepository(
        categories: [_category('living-room', 'Living Room')],
      ),
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(_routerApp(router));
    await tester.pumpAndSettle();

    final node = tester.getSemantics(find.byType(CategoryTile));
    expect(node.label, 'Living Room, browse furniture by category');
    expect(
      node.getSemanticsData().hasAction(SemanticsAction.tap),
      isTrue,
      reason: 'A control must expose a tap action, not only a label.',
    );

    tester.semantics.performAction(
      find.semantics.byLabel('Living Room, browse furniture by category'),
      SemanticsAction.tap,
    );
    await tester.pumpAndSettle();

    expect(
      find.text('detail:living-room'),
      findsOneWidget,
      reason:
          'A screen-reader activation must reach the category landing page.',
    );
    handle.dispose();
  });
}

CategorySummary _category(String slug, String name, {String? assetPath}) =>
    CategorySummary(
      id: 'cat_${slug.replaceAll('-', '_')}',
      name: name,
      slug: slug,
      image: assetPath == null
          ? null
          : CategoryImage(url: '', assetPath: assetPath),
    );

Widget _app(CategoryRepository repository) => MaterialApp.router(
  theme: AppTheme.light(),
  routerConfig: _router(repository),
);

Widget _scaledApp(CategoryRepository repository, double scale) =>
    MaterialApp.router(
      theme: AppTheme.light(),
      routerConfig: _router(repository),
      builder: (context, child) => MediaQuery(
        data: MediaQueryData(textScaler: TextScaler.linear(scale)),
        child: child!,
      ),
    );

Widget _routerApp(GoRouter router) =>
    MaterialApp.router(theme: AppTheme.light(), routerConfig: router);

GoRouter _router(CategoryRepository repository) => GoRouter(
  initialLocation: AppRoutes.categories,
  routes: <RouteBase>[
    GoRoute(
      path: AppRoutes.categories,
      name: AppRoutes.categoriesName,
      builder: (_, _) => CategoriesScreen(repository: repository),
      routes: <RouteBase>[
        GoRoute(
          path: AppRoutes.categoryPath,
          name: AppRoutes.categoryName,
          builder: (_, state) => Scaffold(
            body: Text('detail:${state.pathParameters['categoryId']}'),
          ),
        ),
      ],
    ),
  ],
);

class _FakeCategoryRepository implements CategoryRepository {
  _FakeCategoryRepository({
    this.categories = const <CategorySummary>[],
    this.error,
    this.retryWith,
    this.pending = false,
  });

  final List<CategorySummary> categories;
  final Object? error;
  final List<CategorySummary>? retryWith;
  final bool pending;
  final Completer<void> gate = Completer<void>();
  int calls = 0;

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    calls++;
    if (pending) await gate.future;
    if (error != null && calls == 1) throw error!;
    final visible = calls > 1 && retryWith != null ? retryWith! : categories;
    return CategoryPage(
      categories: visible,
      pagination: const ApiPagination(
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
  }) async => throw UnimplementedError();
}
