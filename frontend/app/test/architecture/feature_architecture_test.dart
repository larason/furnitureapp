import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';
import 'package:sl_furnitures/core/feature_dependencies.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';

import '../support/test_config.dart';

void main() {
  test('feature dependencies use injected shared services', () {
    final apiClient = ApiClient(config: localTestConfig());
    final authSession = _FakeAuthSession();
    final diagnostics = DefaultAppDiagnostics(
      enabled: true,
      sink: InMemoryDiagnosticSink(),
    );
    final dependencies = FeatureDependencies(
      apiClient: apiClient,
      authSession: authSession,
      diagnostics: diagnostics,
    );

    expect(dependencies.apiClient, same(apiClient));
    expect(dependencies.authSession, same(authSession));
    expect(dependencies.diagnostics, same(diagnostics));

    apiClient.close();
    authSession.dispose();
  });

  test('public feature composition does not require an auth session', () {
    final apiClient = ApiClient(config: localTestConfig());
    final dependencies = FeatureDependencies(apiClient: apiClient);

    expect(dependencies.authSession, isNull);
    apiClient.close();
  });

  test('the central router keeps deferred commerce paths unregistered', () {
    final authSession = _FakeAuthSession();
    final router = AppRouter.create(
      authState: authSession,
      getAuthStatus: () => authSession.status,
    );

    expect(router, isA<GoRouter>());
    expect(router.namedLocation(AppRoutes.productsName), AppRoutes.products);
    for (final deferred in <String>[
      'checkout',
      'cart',
      'payment',
      'orders',
      'favorites',
    ]) {
      expect(
        () => router.namedLocation(deferred),
        throwsA(isA<AssertionError>()),
        reason: 'The request-only release must not register "$deferred".',
      );
    }

    router.dispose();
    authSession.dispose();
  });

  test('product detail is a public route with no authentication guard', () {
    final authSession = _FakeAuthSession();
    final router = AppRouter.create(
      authState: authSession,
      getAuthStatus: () => authSession.status,
      catalogRepository: const _EmptyCatalogRepository(),
      categoryRepository: const _EmptyCategoryRepository(),
    );

    expect(
      router.namedLocation(
        AppRoutes.productName,
        pathParameters: <String, String>{'productId': 'modern-3-seater-sofa'},
      ),
      '/products/modern-3-seater-sofa',
    );
    expect(
      AppRoutes.isProtectedPath(AppRoutes.product('modern-3-seater-sofa')),
      isFalse,
    );
    expect(
      AppRoutes.isAuthenticationPath(AppRoutes.product('modern-3-seater-sofa')),
      isFalse,
    );

    router.dispose();
    authSession.dispose();
  });
}

class _EmptyCatalogRepository implements CatalogRepository {
  const _EmptyCatalogRepository();

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
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

class _EmptyCategoryRepository implements CategoryRepository {
  const _EmptyCategoryRepository();

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async => const CategoryPage(
    categories: <CategorySummary>[],
    pagination: ApiPagination(
      currentPage: 1,
      perPage: 20,
      total: 0,
      lastPage: 1,
      hasNext: false,
      hasPrevious: false,
    ),
  );

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) => throw UnimplementedError();
}

class _FakeAuthSession extends ChangeNotifier implements AuthSession {
  @override
  ClerkAuthStatus status = ClerkAuthStatus.signedOut;

  @override
  bool get isSignedIn => status == ClerkAuthStatus.signedIn;

  @override
  Future<String?> getToken() async => null;

  @override
  Future<void> signOut() async {
    status = ClerkAuthStatus.signedOut;
    notifyListeners();
  }
}
