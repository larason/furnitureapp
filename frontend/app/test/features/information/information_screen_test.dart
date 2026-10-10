import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/information/presentation/about_us_screen.dart';
import 'package:sl_furnitures/features/information/presentation/privacy_policy_screen.dart';
import 'package:sl_furnitures/features/information/presentation/terms_conditions_screen.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('About Us renders approved content and contact action', (
    tester,
  ) async {
    await tester.pumpWidget(_app(const AboutUsScreen()));

    expect(find.text('About SL Furnitures'), findsOneWidget);
    expect(find.text('Made-to-Order Furniture'), findsOneWidget);
    expect(find.text('Contact us'), findsOneWidget);
    expect(find.text('Buy now'), findsNothing);
  });

  testWidgets('Privacy Policy renders the complete bundled document', (
    tester,
  ) async {
    final source = _readSource('../../privacy-policy.txt');
    await tester.pumpWidget(_app(const PrivacyPolicyScreen()));
    await _pumpDocument(tester);

    final document = tester.widget<Text>(
      find.byWidgetPredicate(
        (widget) => widget is Text && widget.data == source,
      ),
    );
    expect(document.data, source);
    expect(document.data, contains('We reserve the right to make changes'));
  });

  testWidgets('Terms and Conditions renders the complete bundled document', (
    tester,
  ) async {
    final source = _readSource('../../terms-of-service.txt');
    await tester.pumpWidget(_app(const TermsConditionsScreen()));
    await _pumpDocument(tester);

    final document = tester.widget<Text>(
      find.byWidgetPredicate(
        (widget) => widget is Text && widget.data == source,
      ),
    );
    expect(document.data, source);
    expect(document.data, contains('[email address]'));
  });

  test('legal sources and registered assets match byte-for-byte', () async {
    final pairs = <(String, String)>[
      ('../../privacy-policy.txt', 'assets/legal/privacy-policy.txt'),
      ('../../terms-of-service.txt', 'assets/legal/terms-of-service.txt'),
    ];

    for (final (sourcePath, assetPath) in pairs) {
      final source = await File(sourcePath).readAsBytes();
      final asset = await rootBundle.load(assetPath);
      expect(asset.buffer.asUint8List(), source);
      expect(source, isNotEmpty);
    }
  });
}

Widget _app(Widget child) => MaterialApp(theme: AppTheme.light(), home: child);

String _readSource(String path) => utf8.decode(File(path).readAsBytesSync());

Future<void> _pumpDocument(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 100));
}
