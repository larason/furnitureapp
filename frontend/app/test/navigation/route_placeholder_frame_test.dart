import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  late ValueNotifier<ClerkAuthStatus> status;
  late GoRouter router;

  setUp(() {
    status = ValueNotifier(ClerkAuthStatus.signedOut);
    router = AppRouter.create(
      authState: status,
      getAuthStatus: () => status.value,
    );
  });

  tearDown(() {
    router.dispose();
    status.dispose();
  });

  Future<void> pump(WidgetTester tester) => tester.pumpWidget(
    MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
  );

  group('shell routes', () {
    const shellFallbacks = <String, String>{
      AppRoutes.home: 'Home',
      AppRoutes.products: 'Products',
      AppRoutes.categories: 'Categories',
      AppRoutes.search: 'Search',
      AppRoutes.furnitureRequests: 'Furniture request',
      AppRoutes.contact: 'Contact',
    };

    for (final entry in shellFallbacks.entries) {
      testWidgets('${entry.key} fallback keeps only the shell app bar', (
        tester,
      ) async {
        // No repositories are supplied, so each shell route resolves to its
        // placeholder fallback while still rendering inside the shell.
        await pump(tester);

        router.go(entry.key);
        await tester.pumpAndSettle();

        expect(find.byType(AppBar), findsOneWidget);
        expect(find.byKey(const Key('navigation.app_bar')), findsOneWidget);
        expect(
          find.text(entry.value),
          findsWidgets,
          reason: 'The placeholder still names the unavailable screen.',
        );
      });
    }

    testWidgets('a protected fallback stays inside the shell', (
      tester,
    ) async {
      status.value = ClerkAuthStatus.signedIn;
      await pump(tester);

      router.go(AppRoutes.account);
      await tester.pumpAndSettle();

      // Signed in, so the guard admits the route and it falls back because no
      // profile repository was supplied.
      expect(find.text('Account'), findsWidgets);
      expect(find.byKey(const Key('navigation.app_bar')), findsOneWidget);
      expect(find.byType(AppBar), findsOneWidget);
    });

    testWidgets('an unknown route owns its own frame', (tester) async {
      await pump(tester);

      router.go('/not-a-route');
      await tester.pumpAndSettle();

      // The router-level errorBuilder replaces the whole page, so this route
      // renders outside the navigation shell.
      expect(find.text('Page not found'), findsWidgets);
      expect(find.byKey(const Key('navigation.app_bar')), findsNothing);
      expect(find.byType(AppBar), findsOneWidget);
    });
  });

  group('routes outside the shell', () {
    testWidgets('the auth status page supplies its own frame', (tester) async {
      status.value = ClerkAuthStatus.temporarilyUnavailable;
      await pump(tester);

      router.go(AppRoutes.account);
      await tester.pumpAndSettle();

      expect(find.text('Authentication unavailable'), findsWidgets);
      expect(
        find.byKey(const Key('navigation.app_bar')),
        findsNothing,
        reason: 'This route renders outside the navigation shell.',
      );
      expect(find.byType(AppBar), findsOneWidget);
    });
  });
}