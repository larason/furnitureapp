import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail_repository.dart';
import 'package:sl_furnitures/features/product_detail/presentation/product_detail_screen.dart';
import 'package:sl_furnitures/features/product_detail/presentation/widgets/product_gallery.dart';
import 'package:sl_furnitures/features/product_detail/presentation/widgets/product_information.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

import '../../support/cat002_payload.dart';

void main() {
  testWidgets('shows a stable loading state while the product is pending', (
    tester,
  ) async {
    final repository = _StubProductDetailRepository(pending: true);

    await tester.pumpWidget(_app(repository));
    await tester.pump();

    expect(find.text('Loading furniture'), findsOneWidget);
    expect(tester.takeException(), isNull);
    repository.gate.complete();
    await tester.pumpAndSettle();
  });

  testWidgets('renders the name, price, status, and description', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);
    expect(find.text('From TZS 1,250,000'), findsOneWidget);
    expect(find.text('Made to order'), findsWidgets);
    expect(find.text('Premium handcrafted living room sofa.'), findsOneWidget);
    expect(find.text('Description'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('shows the product price as an indicative figure, not a quote', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.textContaining('Indicative starting price'), findsOneWidget);
    expect(find.textContaining('quotation'), findsNothing);
    expect(find.textContaining('discount'), findsNothing);
  });

  testWidgets('updates the price when an option is selected', (tester) async {
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    final option = find.byKey(
      const ValueKey<String>('product_detail.variant.var_beige'),
    );
    await tester.ensureVisible(option);
    await tester.pumpAndSettle();
    await tester.tap(option);
    await tester.pumpAndSettle();

    expect(find.text('TZS 1,280,000'), findsOneWidget);
    expect(find.text('From TZS 1,280,000'), findsNothing);
    expect(
      find.textContaining('Price for the selected option'),
      findsOneWidget,
      reason: 'A chosen option is not a starting price.',
    );
    expect(find.textContaining('Indicative starting price'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('exposes the option list as a single-choice group', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.text('Available options'), findsOneWidget);
    expect(find.text('Charcoal Grey'), findsOneWidget);
    expect(find.textContaining('SOFA-MOD-3S-GRY'), findsOneWidget);
    expect(
      find.byType(RadioListTile<String>),
      findsNWidgets(2),
      reason: 'No variant detail may be fetched or invented beyond CAT-002.',
    );
    handle.dispose();
  });

  testWidgets('navigates to the canonical category slug', (tester) async {
    final router = _router(_StubProductDetailRepository());

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Living Room'));
    await tester.pumpAndSettle();

    expect(find.text('category:living-room'), findsOneWidget);
    router.dispose();
  });

  testWidgets('reports a non-public product as unavailable', (tester) async {
    final repository = _StubProductDetailRepository(
      error: const ApiError(
        statusCode: 404,
        errors: <ApiErrorItem>[
          ApiErrorItem(
            code: 'RESOURCE_NOT_FOUND',
            message: 'The requested product was not found.',
          ),
        ],
      ),
    );
    final router = _router(repository);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();

    expect(find.text('This furniture is unavailable'), findsOneWidget);
    expect(
      find.textContaining('Premium handcrafted'),
      findsNothing,
      reason: 'A missing product must not leak any product information.',
    );

    await tester.tap(find.text('Browse furniture'));
    await tester.pumpAndSettle();
    expect(find.text('catalog'), findsOneWidget);
    router.dispose();
  });

  testWidgets('offers an explicit retry for a network failure', (tester) async {
    final repository = _StubProductDetailRepository(
      error: const ApiTransportException(
        kind: ApiTransportFailureKind.connection,
      ),
    );

    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    expect(find.text('Connection problem'), findsOneWidget);

    repository.error = null;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(repository.calls, 2);
    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);
  });

  testWidgets('keeps the product visible when a refresh fails', (tester) async {
    tester.view.physicalSize = const Size(360, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final repository = _StubProductDetailRepository();
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    repository.error = const ApiTransportException(
      kind: ApiTransportFailureKind.timeout,
    );
    await tester.fling(
      find.byType(SingleChildScrollView),
      const Offset(0, 400),
      1200,
    );
    await tester.pumpAndSettle();

    expect(repository.calls, 2);
    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);
    expect(find.textContaining('did not complete'), findsOneWidget);
  });

  testWidgets('never shows a cart, checkout, or payment control', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    for (final forbidden in <String>[
      'Add to cart',
      'Buy now',
      'Checkout',
      'Pay',
      'Reserve',
    ]) {
      expect(find.text(forbidden), findsNothing, reason: 'Found "$forbidden".');
    }
  });

  testWidgets('presents the request action as an unavailable preview', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.text('Request this furniture'), findsWidgets);
    expect(
      find.text('Requesting is not available in the app yet.'),
      findsOneWidget,
    );
    final button = tester.widget<ButtonStyleButton>(
      find.widgetWithText(FilledButton, 'Request this furniture'),
    );
    expect(
      button.onPressed,
      isNull,
      reason: 'The action must never look like it submitted a request.',
    );
    handle.dispose();
  });

  testWidgets('reflows at 2x text scaling without clipping prices', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _StubProductDetailRepository(),
        textScale: 2,
        detail: ProductDetail.fromJson(
          cat002Product(
            name: 'Handcrafted Extra-Wide Modular Sectional',
            variants: <Object?>[
              cat002Variant(
                id: 'var_long',
                name: 'Extra-Wide Chaise Configuration',
                amount: 24500000,
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('From TZS 1,250,000'), findsOneWidget);
    expect(find.text('Extra-Wide Chaise Configuration'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('reflows a narrow screen without overflowing', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);
    expect(find.text('From TZS 1,250,000'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('stays usable in landscape', (tester) async {
    tester.view.physicalSize = const Size(844, 390);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('composes photography beside the information on a wide screen', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1280, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    expect(find.byType(ProductGallery), findsOneWidget);
    expect(find.byType(ProductInformation), findsOneWidget);
    final gallery = tester.getRect(find.byType(PageView));
    final information = tester.getRect(find.byType(ProductInformation));
    expect(gallery.left, lessThan(information.left));
    expect(information.right, greaterThan(gallery.left));
    expect(tester.takeException(), isNull);
  });

  testWidgets('exposes one semantic product heading and image labels', (
    tester,
  ) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_app(_StubProductDetailRepository()));
    await tester.pumpAndSettle();

    final heading = find.descendant(
      of: find.byType(ProductInformation),
      matching: find.text('Modern 3-Seater Fabric Sofa'),
    );
    expect(
      tester.getSemantics(heading),
      matchesSemantics(label: 'Modern 3-Seater Fabric Sofa', isHeader: true),
    );
    expect(find.bySemanticsLabel('Front view'), findsOneWidget);
    handle.dispose();
  });

  testWidgets('rebuilds when the route resolves a different product', (
    tester,
  ) async {
    final repository = _StubProductDetailRepository();
    final router = _router(repository);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();
    expect(find.text('Modern 3-Seater Fabric Sofa'), findsWidgets);

    repository.detail = ProductDetail.fromJson(
      cat002Product(slug: 'barrel-chair', name: 'Barrel Chair'),
    );
    router.go(AppRoutes.product('barrel-chair'));
    await tester.pumpAndSettle();

    expect(find.text('Barrel Chair'), findsWidgets);
    expect(
      find.text('Modern 3-Seater Fabric Sofa'),
      findsNothing,
      reason: 'A previous product must never appear under a new identifier.',
    );
    router.dispose();
  });

  testWidgets('renders a product published without photography or options', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _StubProductDetailRepository(),
        detail: ProductDetail.fromJson(
          cat002Product(
            images: <Object?>[],
            primaryImage: null,
            variants: <Object?>[],
            description: '   ',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.bySemanticsLabel('No product photograph available'),
      findsOneWidget,
    );
    expect(find.text('Available options'), findsNothing);
    expect(find.text('Description'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

Widget _app(
  _StubProductDetailRepository repository, {
  ProductDetail? detail,
  double textScale = 1,
}) {
  if (detail != null) repository.detail = detail;
  final app = MaterialApp.router(
    theme: AppTheme.light(),
    routerConfig: _router(repository),
  );
  if (textScale == 1) return app;
  return Builder(
    builder: (context) => MediaQuery(
      data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
      child: app,
    ),
  );
}

GoRouter _router(ProductDetailRepository repository) => GoRouter(
  initialLocation: AppRoutes.product('modern-3-seater-fabric-sofa'),
  routes: <RouteBase>[
    GoRoute(
      path: AppRoutes.products,
      name: AppRoutes.productsName,
      builder: (_, _) => const Scaffold(body: Text('catalog')),
    ),
    GoRoute(
      path: AppRoutes.categories,
      name: AppRoutes.categoriesName,
      builder: (_, _) => const Scaffold(body: Text('categories-index')),
      routes: <RouteBase>[
        GoRoute(
          path: AppRoutes.categoryPath,
          name: AppRoutes.categoryName,
          builder: (_, state) => Scaffold(
            body: Text('category:${state.pathParameters['categoryId']}'),
          ),
        ),
      ],
    ),
    GoRoute(
      path: '${AppRoutes.products}/${AppRoutes.productPath}',
      name: AppRoutes.productName,
      builder: (_, state) => ProductDetailScreen(
        identifier: state.pathParameters['productId']!,
        repository: repository,
      ),
    ),
  ],
);

class _StubProductDetailRepository implements ProductDetailRepository {
  _StubProductDetailRepository({this.error, this.pending = false});

  ProductDetail detail = ProductDetail.fromJson(cat002Product());
  Object? error;
  final bool pending;
  final Completer<void> gate = Completer<void>();
  int calls = 0;

  @override
  Future<ProductDetail> getProduct(
    String product, {
    RequestCancellation? cancellation,
  }) async {
    calls++;
    if (pending) await gate.future;
    if (error != null) throw error!;
    return detail;
  }
}
