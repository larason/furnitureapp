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
import '../features/product_detail/data/product_detail.dart';
import '../features/enquiries/data/enquiry_draft.dart';
import '../features/enquiries/data/enquiry_repository.dart';
import '../features/enquiries/presentation/enquiry_screen.dart';
import '../features/account/data/profile_repository.dart';
import '../features/account/presentation/account_screen.dart';
import '../features/account/presentation/authentication_screen.dart';
import '../features/furniture_requests/data/furniture_request.dart';
import '../features/furniture_requests/data/furniture_request_repository.dart';
import '../features/furniture_requests/presentation/furniture_request_screen.dart';
import '../features/information/presentation/about_us_screen.dart';
import '../features/information/presentation/open_source_licenses_screen.dart';
import '../features/information/presentation/privacy_policy_screen.dart';
import '../features/information/presentation/terms_conditions_screen.dart';
import '../core/auth/auth_session.dart';
import '../theme/app_spacing.dart';
import 'app_routes.dart';
import 'customer_navigation_shell.dart';
import 'route_guard.dart';

abstract final class AppRouter {
  static GoRouter create({
    required Listenable authState,
    required ClerkAuthStatus Function() getAuthStatus,
    AppDiagnostics diagnostics = const NoopAppDiagnostics(),
    CatalogRepository? catalogRepository,
    CategoryRepository? categoryRepository,
    ProductDetailRepository? productDetailRepository,
    FurnitureRequestRepository? furnitureRequestRepository,
    EnquiryRepository? enquiryRepository,
    ProfileRepository? profileRepository,
    AuthSession? authSession,
    bool clerkUiEnabled = false,
    bool showFixtureHero = false,
  }) {
    final guard = RouteGuard(getAuthStatus, diagnostics: diagnostics);
    return GoRouter(
      initialLocation: AppRoutes.home,
      refreshListenable: authState,
      redirect: guard.redirect,
      routes: <RouteBase>[
        ShellRoute(
          builder: (context, state, child) => CustomerNavigationShell(
            location: state.uri.path,
            authState: authState,
            getAuthStatus: getAuthStatus,
            categoryRepository: categoryRepository,
            child: child,
          ),
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
              builder: (_, state) => _furnitureRequestScreen(
                identifier: null,
                extra: state.extra,
                furnitureRequestRepository: furnitureRequestRepository,
                authSession: authSession,
                diagnostics: diagnostics,
              ),
              routes: <RouteBase>[
                GoRoute(
                  path: AppRoutes.furnitureRequestProductPath,
                  builder: (_, state) => _furnitureRequestScreen(
                    identifier: state.pathParameters['productId'],
                    extra: state.extra,
                    furnitureRequestRepository: furnitureRequestRepository,
                    authSession: authSession,
                    diagnostics: diagnostics,
                  ),
                ),
              ],
            ),
            GoRoute(
              name: AppRoutes.contactName,
              path: AppRoutes.contact,
              builder: (_, state) => _enquiryScreen(
                extra: state.extra,
                enquiryRepository: enquiryRepository,
                authSession: authSession,
                diagnostics: diagnostics,
              ),
            ),
            GoRoute(
              name: AppRoutes.aboutName,
              path: AppRoutes.about,
              builder: (_, _) => const AboutUsScreen(),
            ),
            GoRoute(
              name: AppRoutes.privacyPolicyName,
              path: AppRoutes.privacyPolicy,
              builder: (_, _) => const PrivacyPolicyScreen(),
            ),
            GoRoute(
              name: AppRoutes.termsAndConditionsName,
              path: AppRoutes.termsAndConditions,
              builder: (_, _) => const TermsConditionsScreen(),
            ),
            GoRoute(
              name: AppRoutes.accountName,
              path: AppRoutes.account,
              builder: (_, _) => profileRepository == null
                  ? const _RoutePlaceholder(title: 'Account')
                  : AccountScreen(repository: profileRepository),
            ),
          ],
        ),
        GoRoute(
          name: AppRoutes.openSourceLicensesName,
          path: AppRoutes.openSourceLicenses,
          builder: (_, _) => const OpenSourceLicensesScreen(),
        ),
        GoRoute(
          name: AppRoutes.signInName,
          path: AppRoutes.signIn,
          builder: (context, _) => clerkUiEnabled
              ? const AuthenticationScreen()
              : _unavailableAuthenticationRoute(context, 'Sign in'),
        ),
        GoRoute(
          name: AppRoutes.signUpName,
          path: AppRoutes.signUp,
          builder: (context, _) => clerkUiEnabled
              ? const AuthenticationScreen()
              : _unavailableAuthenticationRoute(context, 'Sign up'),
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
        return const _RouteErrorScreen(standalone: true);
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

  static Widget _unavailableAuthenticationRoute(
    BuildContext context,
    String title,
  ) => _StandaloneRoutePage(
    title: title,
    child: _RoutePlaceholder(
      title: title,
      detail: 'Authentication is unavailable in this build.',
      actions: <Widget>[
        OutlinedButton(
          key: const ValueKey<String>('auth_unavailable.home_button'),
          onPressed: () => context.go(AppRoutes.home),
          child: const Text('Back to home'),
        ),
      ],
    ),
  );

  static Widget _furnitureRequestScreen({
    required String? identifier,
    required Object? extra,
    required FurnitureRequestRepository? furnitureRequestRepository,
    required AuthSession? authSession,
    required AppDiagnostics diagnostics,
  }) {
    final productId = identifier == null
        ? null
        : _validIdentifier(identifier, diagnostics);
    if (identifier != null && productId == null) {
      return const _RouteErrorScreen(invalidParameter: true);
    }
    if (furnitureRequestRepository == null) {
      return const _RoutePlaceholder(title: 'Furniture request');
    }
    return FurnitureRequestScreen(
      repository: furnitureRequestRepository,
      authSession: authSession,
      product: _requestProduct(extra, productId),
    );
  }

  static FurnitureRequestProductContext? _requestProduct(
    Object? value,
    String? productId,
  ) {
    if (value is ProductDetail && value.productType == 'MADE_TO_ORDER') {
      return FurnitureRequestProductContext(
        id: value.id,
        name: value.name,
        imageUrl: value.primaryImage?.url,
        imageAlt: value.primaryImage?.altText,
      );
    }
    if (productId == null) return null;
    return FurnitureRequestProductContext(id: productId);
  }

  /// Public contact and enquiry intake.
  ///
  /// Product context is only ever taken from an already-loaded CAT-002 detail
  /// passed by an in-app caller; a deep link carries no product, so no
  /// identifier is inferred from a name or from browsing history.
  static Widget _enquiryScreen({
    required Object? extra,
    required EnquiryRepository? enquiryRepository,
    required AuthSession? authSession,
    required AppDiagnostics diagnostics,
  }) {
    if (enquiryRepository == null) {
      return const _RoutePlaceholder(title: 'Contact');
    }
    return EnquiryScreen(
      repository: enquiryRepository,
      authSession: authSession,
      product: _enquiryProduct(extra),
    );
  }

  static EnquiryProductContext? _enquiryProduct(Object? value) {
    if (value is! ProductDetail) return null;
    return EnquiryProductContext(
      id: value.id,
      name: value.name,
      imageUrl: value.primaryImage?.url,
      imageAlt: value.primaryImage?.altText,
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

/// Placeholder content, without any page frame.
///
/// A shell route already renders inside [CustomerNavigationShell], which
/// supplies the only [Scaffold] and [AppBar] on screen. Adding another here
/// would nest a second app bar inside the shell body, so shell fallbacks use
/// this body-only form.
class _RoutePlaceholder extends StatelessWidget {
  const _RoutePlaceholder({
    required this.title,
    this.detail,
    this.actions = const <Widget>[],
  });

  final String title;
  final String? detail;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    return Material(
      type: MaterialType.transparency,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
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
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                  if (actions.isNotEmpty) ...<Widget>[
                    const SizedBox(height: AppSpacing.space5),
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: AppSpacing.space3,
                      runSpacing: AppSpacing.space3,
                      children: actions,
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

/// A standalone page for routes rendered outside
/// [CustomerNavigationShell].
///
/// These routes have no shell to supply page furniture, so they own their
/// [Scaffold] and [AppBar].
class _StandaloneRoutePage extends StatelessWidget {
  const _StandaloneRoutePage({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(title)),
    body: child,
  );
}

/// Shown while the session cannot be resolved yet.
///
/// This is a waiting state, never a dead end: it always offers a way back to
/// the public catalog, and a way into sign-in whenever the customer still has
/// something to complete there.
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
      builder: (context, _) {
        final status = getAuthStatus();
        final title = _titleFor(status);
        return _StandaloneRoutePage(
          title: title,
          child: _RoutePlaceholder(
            title: title,
            detail: _detailFor(status),
            actions: <Widget>[
              if (status == ClerkAuthStatus.actionRequired)
                FilledButton(
                  key: const ValueKey<String>('auth_status.sign_in_button'),
                  onPressed: () => context.go(AppRoutes.signIn),
                  child: const Text('Sign in'),
                ),
              OutlinedButton(
                key: const ValueKey<String>('auth_status.home_button'),
                onPressed: () => context.go(AppRoutes.home),
                child: const Text('Back to home'),
              ),
            ],
          ),
        );
      },
    );
  }

  static String _titleFor(ClerkAuthStatus status) => switch (status) {
    ClerkAuthStatus.initializing => 'Checking session',
    ClerkAuthStatus.actionRequired => 'Action required',
    ClerkAuthStatus.temporarilyUnavailable => 'Authentication unavailable',
    ClerkAuthStatus.signedIn || ClerkAuthStatus.signedOut => 'Checking session',
  };

  static String _detailFor(ClerkAuthStatus status) => switch (status) {
    ClerkAuthStatus.initializing =>
      'We are confirming your sign-in status. This will only take a moment.',
    ClerkAuthStatus.actionRequired =>
      'Your account still needs attention before private pages can open.',
    ClerkAuthStatus.temporarilyUnavailable =>
      'Signing in is temporarily unavailable. The rest of the app still works.',
    ClerkAuthStatus.signedIn ||
    ClerkAuthStatus.signedOut => 'We are confirming your sign-in status.',
  };
}

class _RouteErrorScreen extends StatelessWidget {
  const _RouteErrorScreen({
    this.invalidParameter = false,
    this.standalone = false,
  });

  final bool invalidParameter;

  /// Whether this replaces the whole page rather than rendering inside the
  /// navigation shell.
  final bool standalone;

  @override
  Widget build(BuildContext context) {
    final title = invalidParameter ? 'Invalid link' : 'Page not found';
    final placeholder = _RoutePlaceholder(title: title);
    if (!standalone) return placeholder;
    return _StandaloneRoutePage(title: title, child: placeholder);
  }
}
