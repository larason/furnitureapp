import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/theme/app_theme.dart';
import 'package:sl_furnitures/theme/tokens/generated_tokens.dart';

/// Automated checks supplement manual review; they do not establish WCAG
/// conformance on their own.
double _linear(double channel) => channel <= 0.03928
    ? channel / 12.92
    : math.pow((channel + 0.055) / 1.055, 2.4).toDouble();

double _luminance(Color color) =>
    0.2126 * _linear(color.r) +
    0.7152 * _linear(color.g) +
    0.0722 * _linear(color.b);

double _contrast(Color foreground, Color background) {
  final a = _luminance(foreground);
  final b = _luminance(background);
  return (math.max(a, b) + 0.05) / (math.min(a, b) + 0.05);
}

void main() {
  const textPairs = <(String, Color, Color)>[
    (
      'primary text on canvas',
      GeneratedTokens.textPrimary,
      GeneratedTokens.surfaceCanvas,
    ),
    (
      'primary text on paper',
      GeneratedTokens.textPrimary,
      GeneratedTokens.surfacePaper,
    ),
    (
      'primary text on editorial',
      GeneratedTokens.textPrimary,
      GeneratedTokens.surfaceEditorial,
    ),
    (
      'secondary text on canvas',
      GeneratedTokens.textSecondary,
      GeneratedTokens.surfaceCanvas,
    ),
    (
      'secondary text on paper',
      GeneratedTokens.textSecondary,
      GeneratedTokens.surfacePaper,
    ),
    (
      'inverse text on inverse surface',
      GeneratedTokens.textInverse,
      GeneratedTokens.surfaceInverse,
    ),
    (
      'inverse text on primary action',
      GeneratedTokens.textInverse,
      GeneratedTokens.actionPrimary,
    ),
    (
      'inverse text on success',
      GeneratedTokens.textInverse,
      GeneratedTokens.colorSuccess,
    ),
    (
      'inverse text on warning',
      GeneratedTokens.textInverse,
      GeneratedTokens.colorWarning,
    ),
    (
      'inverse text on danger',
      GeneratedTokens.textInverse,
      GeneratedTokens.colorDanger,
    ),
    (
      'inverse text on info',
      GeneratedTokens.textInverse,
      GeneratedTokens.colorInfo,
    ),
  ];

  for (final (name, foreground, background) in textPairs) {
    test('$name meets 4.5:1', () {
      expect(_contrast(foreground, background), greaterThanOrEqualTo(4.5));
    });
  }

  const indicatorPairs = <(String, Color, Color)>[
    (
      'focus indicator on canvas',
      GeneratedTokens.focusRingColor,
      GeneratedTokens.surfaceCanvas,
    ),
    (
      'focus indicator on paper',
      GeneratedTokens.focusRingColor,
      GeneratedTokens.surfacePaper,
    ),
    (
      'focus indicator on inverse',
      GeneratedTokens.focusRingColor,
      GeneratedTokens.surfaceInverse,
    ),
  ];

  for (final (name, foreground, background) in indicatorPairs) {
    test('$name meets 3:1', () {
      expect(_contrast(foreground, background), greaterThanOrEqualTo(3));
    });
  }

  testWidgets('a primary action meets the 48dp touch target', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: Center(
            child: FilledButton(
              onPressed: () {},
              child: const Text('Request Furniture'),
            ),
          ),
        ),
      ),
    );
    expect(
      tester.getSize(find.byType(FilledButton)).height,
      greaterThanOrEqualTo(48),
    );
  });

  testWidgets('an icon-only action meets the 48dp touch target', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: Center(
            child: IconButton(
              onPressed: () {},
              icon: const Icon(Icons.search),
              tooltip: 'Search',
            ),
          ),
        ),
      ),
    );
    expect(
      tester.getSize(find.byType(IconButton)).height,
      greaterThanOrEqualTo(48),
    );
  });

  Future<double> textWidthAt(WidgetTester tester, double scale) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: MediaQuery(
          data: MediaQueryData(textScaler: TextScaler.linear(scale)),
          child: const Scaffold(body: Center(child: Text('SL Furnitures'))),
        ),
      ),
    );
    return tester.getSize(find.text('SL Furnitures')).width;
  }

  testWidgets('honors enlarged system text scaling', (tester) async {
    final base = await textWidthAt(tester, 1);
    final enlarged = await textWidthAt(tester, 2);
    expect(enlarged, greaterThan(base));
  });
}
