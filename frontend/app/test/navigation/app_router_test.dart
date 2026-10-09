import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_code.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  group('AppRouter', () {
    testWidgets('opens public catalog routes while signed out', (tester) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.product('prod_example'));
      await tester.pumpAndSettle();

      expect(find.text('Product'), findsWidgets);
      expect(find.text('prod_example'), findsOneWidget);
    });

    testWidgets(
      'opens every public route while authentication is unavailable',
      (tester) async {
        final harness = _RouterHarness(ClerkAuthStatus.temporarilyUnavailable);
        await tester.pumpWidget(harness.app);
        final locations = <String, String>{
          AppRoutes.home: 'Home',
          AppRoutes.products: 'Products',
          AppRoutes.category('cat_example'): 'Category',
          AppRoutes.search: 'Search',
          AppRoutes.furnitureRequests: 'Furniture request',
          AppRoutes.contact: 'Contact',
          AppRoutes.signIn: 'Sign in',
          AppRoutes.signUp: 'Sign up',
        };

        for (final entry in locations.entries) {
          harness.router.go(entry.key);
          await tester.pumpAndSettle();
          expect(find.text(entry.value), findsWidgets);
        }
      },
    );

    group('public category routes', () {
      testWidgets('serves the categories index without authentication', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.categories);
        await tester.pumpAndSettle();

        expect(find.text('Furniture by room'), findsOneWidget);
      });

      testWidgets('opens a category deep link by its server slug', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.category('living-room'));
        await tester.pumpAndSettle();

        expect(find.text('Living Room'), findsWidgets);
        expect(find.text('Made to order'), findsWidgets);
      });

      testWidgets('opens a category deep link by its opaque ID', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.category('cat_01h8x8a1b2c3d4e5f6g7h8j9'));
        await tester.pumpAndSettle();

        expect(find.text('Living Room'), findsWidgets);
      });

      testWidgets('rejects a malformed category route parameter', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go('${AppRoutes.categories}/bad%20param');
        await tester.pumpAndSettle();

        expect(find.text('Invalid link'), findsWidgets);
        expect(
          find.text('Living Room'),
          findsNothing,
          reason: 'A malformed parameter must never reach the category screen.',
        );
      });

      testWidgets('shows an unavailable category for an unknown identifier', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(notFound: true),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.category('missing-room'));
        await tester.pumpAndSettle();

        expect(find.text('This category is unavailable'), findsOneWidget);
      });

      testWidgets('returns from category detail to the index with back', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.categories);
        await tester.pumpAndSettle();
        harness.router.push(AppRoutes.category('living-room'));
        await tester.pumpAndSettle();
        await tester.pageBack();
        await tester.pumpAndSettle();

        expect(find.text('Furniture by room'), findsOneWidget);
      });

      testWidgets('keeps the category route public while signed out', (
        tester,
      ) async {
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          catalogRepository: const _EmptyCatalogRepository(),
          categoryRepository: _StubCategoryRepository(),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.category('living-room'));
        await tester.pumpAndSettle();
        expect(find.text('Sign in'), findsNothing);

        harness.setStatus(ClerkAuthStatus.initializing);
        await tester.pumpAndSettle();
        expect(find.text('Living Room'), findsWidgets);
      });
    });

    testWidgets('redirects protected routes to sign in while signed out', (
      tester,
    ) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.account);
      await tester.pumpAndSettle();

      expect(find.text('Sign in'), findsWidgets);
      expect(
        harness.router.routeInformationProvider.value.uri.queryParameters,
        containsPair('continue', AppRoutes.account),
      );
    });

    testWidgets(
      'defers protected-route decisions until auth restoration ends',
      (tester) async {
        final harness = _RouterHarness(ClerkAuthStatus.initializing);
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.account);
        await tester.pumpAndSettle();
        expect(find.text('Checking session'), findsWidgets);

        harness.setStatus(ClerkAuthStatus.signedOut);
        await tester.pumpAndSettle();
        expect(find.text('Sign in'), findsWidgets);
      },
    );

    testWidgets('restores a safe protected destination after authentication', (
      tester,
    ) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.account);
      await tester.pumpAndSettle();
      harness.setStatus(ClerkAuthStatus.signedIn);
      await tester.pumpAndSettle();

      expect(find.text('Account'), findsWidgets);
    });

    testWidgets('rejects external intended destinations', (tester) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedIn);
      await tester.pumpWidget(harness.app);

      harness.router.go('${AppRoutes.signIn}?continue=https://invalid.example');
      await tester.pumpAndSettle();

      expect(find.text('Home'), findsWidgets);
    });

    testWidgets(
      'blocks protected routes for pending and unavailable sessions',
      (tester) async {
        final harness = _RouterHarness(ClerkAuthStatus.actionRequired);
        await tester.pumpWidget(harness.app);

        harness.router.go(AppRoutes.account);
        await tester.pumpAndSettle();
        expect(find.text('Action required'), findsWidgets);

        harness.setStatus(ClerkAuthStatus.temporarilyUnavailable);
        await tester.pumpAndSettle();
        expect(find.text('Authentication unavailable'), findsWidgets);
      },
    );

    testWidgets('uses safe feedback for an unknown route', (tester) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go('/not-a-route');
      await tester.pumpAndSettle();

      expect(find.text('Page not found'), findsWidgets);
    });

    testWidgets('uses safe feedback for an invalid route parameter', (
      tester,
    ) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.product('invalid product'));
      await tester.pumpAndSettle();

      expect(find.text('Invalid link'), findsWidgets);
    });

    testWidgets('removes protected content after sign out', (tester) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedIn);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.account);
      await tester.pumpAndSettle();
      expect(find.text('Account'), findsWidgets);

      harness.setStatus(ClerkAuthStatus.signedOut);
      await tester.pumpAndSettle();
      expect(find.text('Sign in'), findsWidgets);
    });

    testWidgets('returns from product detail to catalog with system back', (
      tester,
    ) async {
      final harness = _RouterHarness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      harness.router.go(AppRoutes.products);
      await tester.pumpAndSettle();
      harness.router.push(AppRoutes.product('prod_example'));
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();

      expect(find.text('Products'), findsWidgets);
    });

    testWidgets('returns from the catalog CTA to home with system back', (
      tester,
    ) async {
      final harness = _RouterHarness(
        ClerkAuthStatus.signedOut,
        catalogRepository: const _EmptyCatalogRepository(),
      );
      await tester.pumpWidget(harness.app);
      await tester.pumpAndSettle();

      await tester.tap(find.text('Explore furniture'));
      await tester.pumpAndSettle();
      expect(find.text('Furniture'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Furniture for the way you live.'), findsOneWidget);
    });

    testWidgets(
      'records unsafe authentication destinations without logging them',
      (tester) async {
        final sink = InMemoryDiagnosticSink();
        final harness = _RouterHarness(
          ClerkAuthStatus.signedOut,
          diagnostics: DefaultAppDiagnostics(enabled: true, sink: sink),
        );
        await tester.pumpWidget(harness.app);

        harness.router.go('/sign-in?continue=https%3A%2F%2Fevil.example');
        await tester.pumpAndSettle();

        expect(
          sink.events.single.code,
          DiagnosticCode.navigationUnsafeDestination,
        );
        expect(sink.events.single.toJson().toString(), isNot(contains('evil')));
      },
    );
  });
}

class _RouterHarness {
  _RouterHarness(
    ClerkAuthStatus status, {
    AppDiagnostics diagnostics = const NoopAppDiagnostics(),
    CatalogRepository? catalogRepository,
    CategoryRepository? categoryRepository,
  }) : _status = ValueNotifier(status) {
    router = AppRouter.create(
      authState: _status,
      getAuthStatus: () => _status.value,
      diagnostics: diagnostics,
      catalogRepository: catalogRepository,
      categoryRepository: categoryRepository,
    );
    addTearDown(dispose);
  }

  final ValueNotifier<ClerkAuthStatus> _status;
  late final GoRouter router;

  Widget get app =>
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router);

  void setStatus(ClerkAuthStatus status) => _status.value = status;

  void dispose() {
    router.dispose();
    _status.dispose();
  }
}

class _EmptyCatalogRepository implements CatalogRepository {
  const _EmptyCatalogRepository();

  @override
  Future<CatalogPage> fetchProducts({
    required int page,
    String? categorySlug,
    RequestCancellation? cancellation,
  }) async => const CatalogPage(
    products: <ProductSummary>[],
    pagination: ApiPagination(
      currentPage: 1,
      perPage: 20,
      total: 0,
      lastPage: 1,
      hasNext: false,
      hasPrevious: false,
    ),
  );
}

class _StubCategoryRepository implements CategoryRepository {
  _StubCategoryRepository({this.notFound = false});

  final bool notFound;

  static const _detail = CategoryDetail(
    id: 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
    name: 'Living Room',
    slug: 'living-room',
  );

  static const _summary = CategorySummary(
    id: 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
    name: 'Living Room',
    slug: 'living-room',
  );

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async => const CategoryPage(
    categories: <CategorySummary>[_summary],
    pagination: ApiPagination(
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
    if (notFound) {
      throw const ApiError(
        statusCode: 404,
        errors: <ApiErrorItem>[
          ApiErrorItem(
            code: 'RESOURCE_NOT_FOUND',
            message: 'The requested category was not found.',
          ),
        ],
      );
    }
    return _detail;
  }
}
