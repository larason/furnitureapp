import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/information/presentation/open_source_licenses_screen.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('uses Flutter LicensePage for registered dependency licenses', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.light(), home: OpenSourceLicensesScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.byType(LicensePage), findsOneWidget);
    expect(find.text('SL Furnitures'), findsOneWidget);
  });
}
