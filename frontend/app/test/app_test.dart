import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/app.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/theme/tokens/generated_tokens.dart';

import 'support/test_config.dart';

void main() {
  testWidgets('launches the app shell with the token-driven brand theme', (
    tester,
  ) async {
    await tester.pumpWidget(SLFurnituresApp(config: localTestConfig()));

    expect(find.byType(MaterialApp), findsOneWidget);

    final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(app.theme!.useMaterial3, isTrue);
    expect(app.theme!.colorScheme.surface, GeneratedTokens.surfaceCanvas);
    expect(app.theme!.colorScheme.primary, GeneratedTokens.actionPrimary);
  });

  testWidgets('renders the hero image only for fixture catalog mode', (
    tester,
  ) async {
    await tester.pumpWidget(
      SLFurnituresApp(
        config: const AppConfig(
          environment: AppEnvironment.local,
          apiBaseUrl: 'http://127.0.0.1:8000',
          catalogDataSource: CatalogDataSource.fixtures,
        ),
      ),
    );

    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Image &&
            widget.image is AssetImage &&
            (widget.image as AssetImage).assetName ==
                'assets/furnitures/fixtures/hero/hero.jpg',
      ),
      findsOneWidget,
    );
  });

  testWidgets('fixture mode extends the same source policy to product detail', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      SLFurnituresApp(
        config: const AppConfig(
          environment: AppEnvironment.local,
          apiBaseUrl: 'http://127.0.0.1:8000',
          catalogDataSource: CatalogDataSource.fixtures,
        ),
      ),
    );
    await tester.pumpAndSettle();

    final explore = find.text('Explore furniture');
    await tester.ensureVisible(explore);
    await tester.pumpAndSettle();
    await tester.tap(explore);
    await tester.pumpAndSettle();
    expect(find.text('Lounge chair'), findsOneWidget);

    final card = find.text('Lounge chair');
    await tester.ensureVisible(card);
    await tester.pumpAndSettle();
    await tester.tap(card);
    await tester.pumpAndSettle();

    expect(find.text('Lounge chair'), findsWidgets);
    expect(find.text('From TZS 100,000'), findsOneWidget);
    expect(find.text('Showing image 1 of 2'), findsOneWidget);
    expect(find.text('Available options'), findsOneWidget);
    expect(
      find.text('Requesting is not available in the app yet.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}
