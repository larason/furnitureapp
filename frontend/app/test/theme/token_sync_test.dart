import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import '../../tool/generate_tokens.dart';
import '../../tool/token_generator.dart';
import '../support/tokens_css.dart';

/// Guards against a canonical token change silently leaving Flutter stale.
///
/// The generator is format-stable, so raw emitted output is compared directly.
/// The CLI additionally runs the formatter, and
/// `dart run tool/generate_tokens.dart --check` performs the same comparison.
void main() {
  test('the committed generated tokens are current with tokens.css', () {
    final expected = generateTokensDart(
      canonicalTokensCss().readAsStringSync(),
      sourceLabel: sourceLabel,
    );
    final generatedFile = File(outputRelativePath);
    expect(
      generatedFile.existsSync(),
      isTrue,
      reason: 'Generated tokens are missing.',
    );
    expect(
      generatedFile.readAsStringSync(),
      expected,
      reason:
          'Generated tokens are stale. Run: dart run tool/generate_tokens.dart',
    );
  });
}
