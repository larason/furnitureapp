import 'package:flutter/material.dart' hide DiagnosticLevel;
import 'package:go_router/go_router.dart';

import '../core/auth/clerk_auth_adapter.dart';
import '../core/diagnostics/app_diagnostics.dart';
import '../core/diagnostics/diagnostic_category.dart';
import '../core/diagnostics/diagnostic_code.dart';
import '../core/diagnostics/diagnostic_event.dart';
import '../core/diagnostics/diagnostic_level.dart';
import '../features/catalog/data/catalog_repository.dart';
import '../features/catalog/presentation/catalog_screen.dart';
import '../features/categories/data/category_repository.dart';
import '../features/categories/presentation/categories_screen.dart';
import '../features/categories/presentation/category_detail_screen.dart';
import '../features/home/presentation/home_screen.dart';
import '../features/product_detail/data/product_detail_repository.dart';
import '../features/search/presentation/search_screen_host.dart';
import '../features/product_detail/presentation/product_detail_screen.dart';
import '../theme/app_spacing.dart';
import 'app_routes.dart';
import 'route_guard.dart';

abstract final class AppRouter {
  static GoRouter create({
    required Listenable authState,
    required ClerkAuthStatus Function() getAuthStatus,
    AppDiagnostics diagnostics = const NoopAppDiagnostics(),
    CatalogRepository? catalogRepository,
    CategoryRepository? categoryRepository,
    ProductDetailRepository? productDetailRepository,
    bool showFixtureHero = false,
  }) {
    final guard = RouteGuard(getAuthStatus, diagnostics: diagnostics);
    return GoRouter(
      initialLocation: AppRoutes.home,
      refreshListenable: authState,
      redirect: guard.redirect,
      routes: <RouteBase>[
        GoRoute(
          name: AppRoutes.homeName,
          path: AppRoutes.home,
          builder: (_, _) => catalogRepository == null
              ? const _RoutePlaceholder(title: 'Home')
              : HomeScreen(
                  repository: catalogRepository,
                  showFixtureHero: showFixtureHero,
                ),
        ),
        GoRoute(
          name: AppRoutes.productsName,
          path: AppRoutes.products,
          builder: (_, _) => catalogRepository == null
              ? const _RoutePlaceholder(title: 'Products')
              : CatalogScreen(repository: catalogRepository),
          routes: <RouteBase>[
            GoRoute(
              name: AppRoutes.productName,
              path: AppRoutes.productPath,
              builder: (_, state) => _productScreen(
                identifier: state.pathParameters['productId'],
                productDetailRepository: productDetailRepository,
                diagnostics: diagnostics,
              ),
            ),
          ],
        ),
        GoRoute(
          name: AppRoutes.categoriesName,
          path: AppRoutes.categories,
          builder: (_, _) => categoryRepository == null
              ? const _RoutePlaceholder(title: 'Categories')
              : CategoriesScreen(repository: categoryRepository),
          routes: <RouteBase>[
            GoRoute(
              name: AppRoutes.categoryName,
              path: AppRoutes.categoryPath,
              builder: (_, state) => _categoryScreen(
                identifier: state.pathParameters['categoryId'],
                categoryRepository: categoryRepository,
                catalogRepository: catalogRepository,
                diagnostics: diagnostics,
              ),
            ),
          ],
        ),
        GoRoute(
          name: AppRoutes.searchName,
          path: AppRoutes.search,
          builder: (_, _) => _searchScreen(
            catalogRepository: catalogRepository,
            categoryRepository: categoryRepository,
          ),
        ),
        GoRoute(
          name: AppRoutes.furnitureRequestsName,
          path: AppRoutes.furnitureRequests,
          builder: (_, _) =>
              const _RoutePlaceholder(title: 'Furniture request'),
        ),
        GoRoute(
          name: AppRoutes.contactName,
          path: AppRoutes.contact,
          builder: (_, _) => const _RoutePlaceholder(title: 'Contact'),
        ),
        GoRoute(
          name: AppRoutes.accountName,
          path: AppRoutes.account,
          builder: (_, _) => const _RoutePlaceholder(title: 'Account'),
        ),
        GoRoute(
          name: AppRoutes.signInName,
          path: AppRoutes.signIn,
          builder: (_, _) => const _RoutePlaceholder(title: 'Sign in'),
        ),
        GoRoute(
          name: AppRoutes.signUpName,
          path: AppRoutes.signUp,
          builder: (_, _) => const _RoutePlaceholder(title: 'Sign up'),
        ),
        GoRoute(
          name: AppRoutes.authStatusName,
          path: AppRoutes.authStatus,
          builder: (_, _) => _AuthStatusPlaceholder(
            authState: authState,
            getAuthStatus: getAuthStatus,
          ),
        ),
      ],
      errorBuilder: (_, _) {
        diagnostics.record(
          DiagnosticEvent.now(
            level: DiagnosticLevel.warning,
            category: DiagnosticCategory.navigation,
            code: DiagnosticCode.navigationRouteUnavailable,
            context: const DiagnosticContext(
              operation: DiagnosticOperation.navigation,
              routeFailure: DiagnosticRouteFailure.unknownRoute,
            ),
          ),
        );
        return const _RouteErrorScreen();
      },
    );
  }

  /// Public search and filtering.
  ///
  /// The screen owns a short-lived [SearchController] bound to this screen's
  /// lifecycle, so returning here starts from the unfiltered catalog rather than
  /// restoring criteria that are no longer on screen. Criteria live in the
  /// screen, never in a global mutable singleton.
  static Widget _searchScreen({
    required CatalogRepository? catalogRepository,
    required CategoryRepository? categoryRepository,
  }) {
    if (catalogRepository == null || categoryRepository == null) {
      return const _RoutePlaceholder(title: 'Search');
    }
    return SearchScreenHost(
      catalogRepository: catalogRepository,
      categoryRepository: categoryRepository,
    );
  }

  /// Public product detail. The route parameter must be a valid identifier
  /// before any request is made, and the screen resolves unknown products from
  /// CAT-002 as an unavailable product rather than as a broken link.
  static Widget _productScreen({
    required String? identifier,
    required ProductDetailRepository? productDetailRepository,
    required AppDiagnostics diagnostics,
  }) {
    final product = _validIdentifier(identifier, diagnostics);
    if (product == null) return const _RouteErrorScreen(invalidParameter: true);
    if (productDetailRepository == null) {
      return _RoutePlaceholder(title: 'Product', detail: product);
    }
    return ProductDetailScreen(
      identifier: product,
      repository: productDetailRepository,
    );
  }

  /// Public category detail. The route parameter must be a valid identifier
  /// before any request is made; unknown categories are resolved by the screen
  /// from CAT-004 and shown as unavailable rather than as a broken link.
  static Widget _categoryScreen({
    required String? identifier,
    required CategoryRepository? categoryRepository,
    required CatalogRepository? catalogRepository,
    required AppDiagnostics diagnostics,
  }) {
    final category = _validIdentifier(identifier, diagnostics);
    if (category == null) {
      return const _RouteErrorScreen(invalidParameter: true);
    }
    if (categoryRepository == null || catalogRepository == null) {
      return _RoutePlaceholder(title: 'Category', detail: category);
    }
    return CategoryDetailScreen(
      identifier: category,
      categoryRepository: categoryRepository,
      catalogRepository: catalogRepository,
    );
  }

  /// Returns the route identifier when it is a valid public resource
  /// identifier, or null after recording the shared invalid-parameter
  /// diagnostic. Every resource route validates before it can issue a request,
  /// and the rejected value is never logged.
  static String? _validIdentifier(
    String? identifier,
    AppDiagnostics diagnostics,
  ) {
    if (AppRoutes.isValidResourceId(identifier)) return identifier;
    diagnostics.record(
      DiagnosticEvent.now(
        level: DiagnosticLevel.warning,
        category: DiagnosticCategory.navigation,
        code: DiagnosticCode.navigationInvalidParameter,
        context: const DiagnosticContext(
          operation: DiagnosticOperation.navigation,
          routeFailure: DiagnosticRouteFailure.invalidParameter,
        ),
      ),
    );
    return null;
  }
}

class _RoutePlaceholder extends StatelessWidget {
  const _RoutePlaceholder({required this.title, this.detail});

  final String title;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.space6),
            child: Semantics(
              header: true,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Text(
                    title,
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  if (detail != null) ...<Widget>[
                    const SizedBox(height: AppSpacing.space3),
                    Text(
                      detail!,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _AuthStatusPlaceholder extends StatelessWidget {
  const _AuthStatusPlaceholder({
    required this.authState,
    required this.getAuthStatus,
  });

  final Listenable authState;
  final ClerkAuthStatus Function() getAuthStatus;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: authState,
      builder: (_, _) {
        final title = switch (getAuthStatus()) {
          ClerkAuthStatus.initializing => 'Checking session',
          ClerkAuthStatus.actionRequired => 'Action required',
          ClerkAuthStatus.temporarilyUnavailable =>
            'Authentication unavailable',
          ClerkAuthStatus.signedIn ||
          ClerkAuthStatus.signedOut => 'Checking session',
        };
        return _RoutePlaceholder(title: title);
      },
    );
  }
}

class _RouteErrorScreen extends StatelessWidget {
  const _RouteErrorScreen({this.invalidParameter = false});

  final bool invalidParameter;

  @override
  Widget build(BuildContext context) {
    final title = invalidParameter ? 'Invalid link' : 'Page not found';
    return _RoutePlaceholder(title: title);
  }
}
