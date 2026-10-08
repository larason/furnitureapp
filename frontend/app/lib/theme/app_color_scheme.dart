import 'package:flutter/material.dart';

import 'tokens/generated_tokens.dart';

/// Explicit light [ColorScheme] built from canonical semantic tokens.
///
/// No `ColorScheme.fromSeed` and no dynamic/wallpaper color: Material generates
/// a palette, it does not generate the SL Furnitures brand. Optional Material
/// roles carry neutral values only, and `surfaceTint` is transparent so
/// elevation never tints a brand surface.
abstract final class AppColorScheme {
  /// Material requires a translucent modal scrim, which the canonical tokens do
  /// not define. Derived from `--surface-inverse`; recorded as a token gap
  /// rather than a new brand colour.
  static final Color scrim = GeneratedTokens.surfaceInverse.withValues(
    alpha: 0.54,
  );

  static final ColorScheme light = ColorScheme(
    brightness: Brightness.light,
    primary: GeneratedTokens.actionPrimary,
    onPrimary: GeneratedTokens.textInverse,
    primaryContainer: GeneratedTokens.actionPrimaryDisabled,
    onPrimaryContainer: GeneratedTokens.textPrimary,
    secondary: GeneratedTokens.textSecondary,
    onSecondary: GeneratedTokens.textInverse,
    secondaryContainer: GeneratedTokens.borderSubtle,
    onSecondaryContainer: GeneratedTokens.textPrimary,
    error: GeneratedTokens.colorDanger,
    onError: GeneratedTokens.textInverse,
    surface: GeneratedTokens.surfaceCanvas,
    onSurface: GeneratedTokens.textPrimary,
    surfaceBright: GeneratedTokens.surfacePaper,
    surfaceContainerLowest: GeneratedTokens.surfacePaper,
    surfaceContainerLow: GeneratedTokens.surfacePaper,
    surfaceContainer: GeneratedTokens.surfacePaper,
    surfaceContainerHigh: GeneratedTokens.surfacePaper,
    surfaceContainerHighest: GeneratedTokens.surfacePaper,
    onSurfaceVariant: GeneratedTokens.textSecondary,
    outline: GeneratedTokens.borderDefault,
    outlineVariant: GeneratedTokens.borderSubtle,
    shadow: GeneratedTokens.elevRaisedColor,
    scrim: scrim,
    inverseSurface: GeneratedTokens.surfaceInverse,
    onInverseSurface: GeneratedTokens.textInverse,
    inversePrimary: GeneratedTokens.surfacePaper,
    surfaceTint: Colors.transparent,
  );
}
