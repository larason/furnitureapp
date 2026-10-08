import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/theme/app_theme.dart';
import 'package:sl_furnitures/theme/preview/theme_preview.dart';

void main() {
  testWidgets('the theme preview harness renders without errors', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.light(), home: const ThemePreviewScreen()),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Theme preview'), findsOneWidget);
    expect(find.text('Primary'), findsOneWidget);
    expect(find.text('Disabled'), findsOneWidget);
    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
