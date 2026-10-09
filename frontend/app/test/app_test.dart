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
                'assets/furnitures/fixtures/hero/chairs-heroimage.webp',
      ),
      findsOneWidget,
    );
  });
}
