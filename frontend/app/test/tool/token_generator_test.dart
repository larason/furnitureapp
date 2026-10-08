import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import '../../tool/token_generator.dart';
import '../support/tokens_css.dart';

void main() {
  late String canonicalCss;
  late String generated;

  setUpAll(() {
    canonicalCss = canonicalTokensCss().readAsStringSync();
    generated = generateTokensDart(canonicalCss);
  });

  test('parses the canonical CSS and resolves var() references', () {
    expect(
      generated,
      contains('static const Color surfaceCanvas = Color(0xFFFCF4ED);'),
    );
    expect(
      generated,
      contains('static const Color actionFocus = Color(0xFF275DC5);'),
    );
  });

  test('converts colours, including rgba alpha and transparent', () {
    expect(generated, contains('Color(0x1F111111)'));
    expect(
      generated,
      contains('static const Color actionSecondary = Color(0x00000000);'),
    );
  });

  test('converts hex alpha from CSS order to Flutter ARGB order', () {
    const declaration = '--color-warm-100: #fcf4ed;';

    String convert(String hex) => generateTokensDart(
      canonicalCss.replaceFirst(declaration, '--color-warm-100: $hex;'),
    );

    // CSS #RRGGBBAA keeps alpha last; Flutter 0xAARRGGBB keeps it first.
    expect(
      convert('#fcf4ed99'),
      contains('static const Color colorWarm100 = Color(0x99FCF4ED);'),
    );
    // CSS #RGBA expands to #RRGGBBAA first, then reorders identically.
    expect(
      convert('#fcf4'),
      contains('static const Color colorWarm100 = Color(0x44FFCCFF);'),
    );
    // Existing 3- and 6-digit behaviour is unchanged.
    expect(
      convert('#fcf4ed'),
      contains('static const Color colorWarm100 = Color(0xFFFCF4ED);'),
    );
    expect(
      convert('#fcf'),
      contains('static const Color colorWarm100 = Color(0xFFFFCCFF);'),
    );
    expect(
      () => convert('#fcf4ed9'),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('Unsupported color value'),
        ),
      ),
    );
  });

  test('converts measurements, durations, ratios, and families', () {
    expect(generated, contains('static const double space10 = 80;'));
    expect(generated, contains('static const double borderWidth = 1.5;'));
    expect(
      generated,
      contains(
        'static const Duration motionFast = Duration(milliseconds: 150);',
      ),
    );
    expect(
      generated,
      contains('static const double mediaProductCard = 4 / 3;'),
    );
    expect(generated, contains('static const List<String> fontDisplay'));
    expect(generated, contains('static const List<String> fontUi'));
    expect(generated, contains("'Young Serif'"));
    expect(
      generated,
      contains('static const FontWeight fontWeightRegular = FontWeight.w400;'),
    );
    expect(generated, contains('static const int zDialog = 300;'));
  });

  test('preserves the generated-file header and source identity', () {
    expect(generated, startsWith('// GENERATED FILE - DO NOT EDIT.'));
    expect(generated, contains('Source: frontend/design-system/tokens.css'));
    expect(generated, contains('dart run tool/generate_tokens.dart'));
  });

  test('produces deterministic, machine-independent output', () {
    final second = generateTokensDart(canonicalCss);
    expect(second, generated);
    expect(generated, isNot(contains(Directory.current.path)));
  });

  test('emits every required token', () {
    for (final name in requiredTokenNames) {
      expect(
        generated,
        contains(dartIdentifier(name)),
        reason: 'Missing generated identifier for --$name',
      );
    }
  });

  test('derives camelCase identifiers without collisions', () {
    expect(dartIdentifier('color-neutral-950'), 'colorNeutral950');
    expect(dartIdentifier('fg-2'), 'fg2');
    expect(dartIdentifier('media-product-card'), 'mediaProductCard');
    expect(dartIdentifier('z-base'), 'zBase');
  });

  test('fails when the :root block is absent', () {
    expect(
      () => generateTokensDart('body { color: red; }'),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('must declare a :root block'),
        ),
      ),
    );
  });

  test('fails on unresolved references', () {
    expect(
      () => generateTokensDart(
        ':root { --surface-canvas: var(--does-not-exist); }',
      ),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('Unresolved token reference --does-not-exist'),
        ),
      ),
    );
  });

  test('fails on circular references', () {
    expect(
      () => generateTokensDart(
        ':root { --a-token: var(--b-token); --b-token: var(--a-token); }',
      ),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('Circular token reference'),
        ),
      ),
    );
  });

  test('fails on missing required tokens', () {
    expect(
      () => generateTokensDart(':root { --color-warm-100: #FCF4ED; }'),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('Missing required tokens'),
        ),
      ),
    );
  });

  test('fails on an unsupported value instead of guessing', () {
    final withUnsupported = canonicalCss.replaceFirst(
      '}',
      '  --unsupported-measure: 12em;\n}',
    );
    expect(
      () => generateTokensDart(withUnsupported),
      throwsA(
        isA<TokenGenerationException>().having(
          (error) => error.message,
          'message',
          contains('Unsupported value for --unsupported-measure'),
        ),
      ),
    );
  });
}
