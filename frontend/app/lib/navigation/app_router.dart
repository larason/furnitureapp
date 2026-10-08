import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/clerk_auth_adapter.dart';
import '../theme/app_spacing.dart';
import 'app_routes.dart';
import 'route_guard.dart';

abstract final class AppRouter {
  static GoRouter create({
    required Listenable authState,
    required ClerkAuthStatus Function() getAuthStatus,
  }) {
    final guard = RouteGuard(getAuthStatus);
    return GoRouter(
      initialLocation: AppRoutes.home,
      refreshListenable: authState,
      redirect: guard.redirect,
      routes: <RouteBase>[
        GoRoute(
          name: AppRoutes.homeName,
          path: AppRoutes.home,
          builder: (_, _) => const _RoutePlaceholder(title: 'Home'),
        ),
        GoRoute(
          name: AppRoutes.productsName,
          path: AppRoutes.products,
          builder: (_, _) => const _RoutePlaceholder(title: 'Products'),
          routes: <RouteBase>[
            GoRoute(
              name: AppRoutes.productName,
              path: AppRoutes.productPath,
              builder: (_, state) => _resourcePlaceholder(
                title: 'Product',
                identifier: state.pathParameters['productId'],
              ),
            ),
          ],
        ),
        GoRoute(
          name: AppRoutes.categoriesName,
          path: AppRoutes.categories,
          builder: (_, _) => const _RoutePlaceholder(title: 'Categories'),
          routes: <RouteBase>[
            GoRoute(
              name: AppRoutes.categoryName,
              path: AppRoutes.categoryPath,
              builder: (_, state) => _resourcePlaceholder(
                title: 'Category',
                identifier: state.pathParameters['categoryId'],
              ),
            ),
          ],
        ),
        GoRoute(
          name: AppRoutes.searchName,
          path: AppRoutes.search,
          builder: (_, _) => const _RoutePlaceholder(title: 'Search'),
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
      errorBuilder: (_, _) => const _RouteErrorScreen(),
    );
  }

  static Widget _resourcePlaceholder({
    required String title,
    required String? identifier,
  }) {
    if (!AppRoutes.isValidResourceId(identifier)) {
      return const _RouteErrorScreen(invalidParameter: true);
    }
    return _RoutePlaceholder(title: title, detail: identifier);
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
