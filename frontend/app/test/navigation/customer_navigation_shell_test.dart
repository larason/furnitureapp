import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/core/network/api_response.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets(
    'renders the shared centered logo with menu and account actions',
    (tester) async {
      final harness = _Harness(ClerkAuthStatus.signedOut);
      await tester.pumpWidget(harness.app);

      expect(find.byKey(const Key('navigation.app_bar')), findsOneWidget);
      expect(find.byKey(const Key('navigation.menu_button')), findsOneWidget);
      expect(
        find.byKey(const Key('navigation.account_button')),
        findsOneWidget,
      );
      expect(find.bySemanticsLabel('SL Furnitures home'), findsOneWidget);
    },
  );

  testWidgets('guest account action opens and closes account options', (
    tester,
  ) async {
    final harness = _Harness(ClerkAuthStatus.signedOut);
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.account_button')));
    await tester.pumpAndSettle();
    expect(find.text('Account'), findsOneWidget);
    expect(find.byKey(const Key('navigation.sign_in_button')), findsOneWidget);
    expect(
      find.byKey(const Key('navigation.create_account_button')),
      findsOneWidget,
    );
    expect(find.text('Orders'), findsNothing);

    await tester.tap(find.byTooltip('Close account options'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('navigation.sign_in_button')), findsNothing);
  });

  testWidgets('guest sheet routes to existing sign-in and sign-up routes', (
    tester,
  ) async {
    final harness = _Harness(ClerkAuthStatus.signedOut);
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.account_button')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('navigation.sign_in_button')));
    await tester.pumpAndSettle();
    expect(harness.location, AppRoutes.signIn);

    harness.router.go(AppRoutes.home);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('navigation.account_button')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('navigation.create_account_button')));
    await tester.pumpAndSettle();
    expect(harness.location, AppRoutes.signUp);
  });

  testWidgets('authenticated account action navigates directly to profile', (
    tester,
  ) async {
    final harness = _Harness(ClerkAuthStatus.signedIn);
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.account_button')));
    await tester.pumpAndSettle();

    expect(harness.location, AppRoutes.account);
    expect(find.byKey(const Key('navigation.sign_in_button')), findsNothing);
  });

  testWidgets('action-required account action opens sign in', (tester) async {
    final harness = _Harness(ClerkAuthStatus.actionRequired);
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.account_button')));
    await tester.pumpAndSettle();

    expect(harness.location, AppRoutes.signIn);
    expect(find.byKey(const Key('navigation.sign_in_button')), findsNothing);
  });

  testWidgets('temporarily unavailable account action is disabled', (
    tester,
  ) async {
    final harness = _Harness(ClerkAuthStatus.temporarilyUnavailable);
    await tester.pumpWidget(harness.app);

    final button = tester.widget<IconButton>(
      find.byKey(const Key('navigation.account_button')),
    );

    expect(button.onPressed, isNull);
  });

  testWidgets('drawer exposes public destinations and dynamic categories', (
    tester,
  ) async {
    final harness = _Harness(
      ClerkAuthStatus.signedOut,
      categoryRepository: _CategoryRepository(),
    );
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.menu_button')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('navigation.search_entry')), findsOneWidget);
    expect(find.text('All furniture'), findsOneWidget);
    expect(find.text('Living Room'), findsOneWidget);
    expect(find.text('Open source licenses'), findsNothing);
    expect(find.text('Checkout'), findsNothing);

    await tester.tap(find.text('Living Room'));
    await tester.pumpAndSettle();
    expect(harness.location, AppRoutes.category('living-room'));
  });

  testWidgets('drawer search entry opens the existing search route', (
    tester,
  ) async {
    final harness = _Harness(ClerkAuthStatus.signedOut);
    await tester.pumpWidget(harness.app);

    await tester.tap(find.byKey(const Key('navigation.menu_button')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('navigation.search_entry')));
    await tester.pumpAndSettle();

    expect(harness.location, AppRoutes.search);
  });
}

class _Harness {
  _Harness(ClerkAuthStatus status, {CategoryRepository? categoryRepository})
    : _status = ValueNotifier<ClerkAuthStatus>(status) {
    router = AppRouter.create(
      authState: _status,
      getAuthStatus: () => _status.value,
      categoryRepository: categoryRepository,
    );
    addTearDown(() {
      router.dispose();
      _status.dispose();
    });
  }

  final ValueNotifier<ClerkAuthStatus> _status;
  late final GoRouter router;

  String get location => router.routeInformationProvider.value.uri.path;
  Widget get app =>
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router);
}

class _CategoryRepository implements CategoryRepository {
  static const _category = CategorySummary(
    id: 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
    name: 'Living Room',
    slug: 'living-room',
  );

  @override
  Future<CategoryDetail> getCategory(
    String category, {
    RequestCancellation? cancellation,
  }) async => CategoryDetail(
    id: _category.id,
    name: _category.name,
    slug: _category.slug,
  );

  @override
  Future<CategoryPage> getCategories({
    required int page,
    RequestCancellation? cancellation,
  }) async => CategoryPage(
    categories: const <CategorySummary>[_category],
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
