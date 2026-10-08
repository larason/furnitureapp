import 'package:flutter/material.dart';

import 'tokens/generated_tokens.dart';

/// Material 3 [TextTheme] mapped from the canonical type scale.
///
/// Young Serif is display-only, mirroring the website's `h1`/`h2`/`h3`
/// treatment (96, 48, 32). Every other role uses the canonical utility family at
/// the canonical 12/14/16/20/24/32 sizes, so no Material default (36/45/57) is
/// allowed to survive.
///
/// Font decisions:
/// - Young Serif is bundled from the OFL asset the website already ships; it is
///   the approved brand display face and is safe to redistribute.
/// - Helvetica Now Text is commercial and is not distributed anywhere in this
///   repository, so utility type resolves to the platform sans. The canonical
///   stack itself terminates in generic `sans-serif`, so this is the documented
///   fallback rather than a substituted brand font.
abstract final class AppTypography {
  /// Registered Flutter family for the bundled Young Serif asset.
  static String get displayFamily => GeneratedTokens.fontDisplay.first;

  /// Terminal fallback of the canonical UI stack.
  static String get utilityFamily => GeneratedTokens.fontUi.last;

  static List<String> get displayFallback =>
      GeneratedTokens.fontDisplay.skip(1).toList();

  /// The token contract defines letter tracking for display type only.
  static const double utilityTracking = 0;

  static final TextStyle displayLarge = _display(GeneratedTokens.text4xl);
  static final TextStyle displayMedium = _display(GeneratedTokens.text3xl);
  static final TextStyle displaySmall = _display(GeneratedTokens.text2xl);
  static final TextStyle headlineLarge = _display(GeneratedTokens.text2xl);
  static final TextStyle headlineMedium = _utility(
    GeneratedTokens.textXl,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle headlineSmall = _utility(
    GeneratedTokens.textLg,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle titleLarge = _utility(
    GeneratedTokens.textLg,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle titleMedium = _utility(
    GeneratedTokens.textBase,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle titleSmall = _utility(
    GeneratedTokens.textSm,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle bodyLarge = _utility(
    GeneratedTokens.textBase,
    GeneratedTokens.fontWeightRegular,
    GeneratedTokens.leadingBody,
  );
  static final TextStyle bodyMedium = _utility(
    GeneratedTokens.textSm,
    GeneratedTokens.fontWeightRegular,
    GeneratedTokens.leadingBody,
  );
  static final TextStyle bodySmall = _utility(
    GeneratedTokens.textXs,
    GeneratedTokens.fontWeightRegular,
    GeneratedTokens.leadingBody,
  );
  static final TextStyle labelLarge = _utility(
    GeneratedTokens.textSm,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle labelMedium = _utility(
    GeneratedTokens.textXs,
    GeneratedTokens.fontWeightMedium,
    GeneratedTokens.leadingSnug,
  );
  static final TextStyle labelSmall = _utility(
    GeneratedTokens.textXs,
    GeneratedTokens.fontWeightRegular,
    GeneratedTokens.leadingSnug,
  );

  static final TextTheme textTheme = TextTheme(
    displayLarge: displayLarge,
    displayMedium: displayMedium,
    displaySmall: displaySmall,
    headlineLarge: headlineLarge,
    headlineMedium: headlineMedium,
    headlineSmall: headlineSmall,
    titleLarge: titleLarge,
    titleMedium: titleMedium,
    titleSmall: titleSmall,
    bodyLarge: bodyLarge,
    bodyMedium: bodyMedium,
    bodySmall: bodySmall,
    labelLarge: labelLarge,
    labelMedium: labelMedium,
    labelSmall: labelSmall,
  );

  static TextStyle _display(double size) => TextStyle(
    fontFamily: displayFamily,
    fontFamilyFallback: displayFallback,
    fontSize: size,
    fontWeight: GeneratedTokens.fontWeightRegular,
    height: GeneratedTokens.leadingDisplay,
    letterSpacing: GeneratedTokens.trackingDisplay,
  );

  static TextStyle _utility(double size, FontWeight weight, double height) =>
      TextStyle(
        fontFamily: utilityFamily,
        fontSize: size,
        fontWeight: weight,
        height: height,
        letterSpacing: utilityTracking,
      );
}
