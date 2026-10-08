import 'package:flutter/material.dart';

import '../app_motion.dart';
import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Action themes for the approved vocabulary: primary, secondary, quiet, and
/// icon-only. Buttons are pills because the canonical MUI button radius is the
/// pill radius; they are flat because elevation is reserved for true layers.
abstract final class AppButtonThemes {
  static const EdgeInsetsGeometry _labelPadding = EdgeInsets.symmetric(
    horizontal: AppSpacing.space6,
    vertical: AppSpacing.space3,
  );
  static const Size _minimumSize = Size(64, 48);
  static const OutlinedBorder _pill = AppRadii.pillShape;

  static FilledButtonThemeData get filled =>
      FilledButtonThemeData(style: _primary);

  static ElevatedButtonThemeData get elevated =>
      ElevatedButtonThemeData(style: _primary);

  static OutlinedButtonThemeData get outlined =>
      OutlinedButtonThemeData(style: _secondary);

  static TextButtonThemeData get text => TextButtonThemeData(style: _quiet);

  static IconButtonThemeData get icon => IconButtonThemeData(
    style: ButtonStyle(
      foregroundColor: WidgetStateProperty.resolveWith<Color>(_iconForeground),
      minimumSize: const WidgetStatePropertyAll<Size>(Size(48, 48)),
      padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(
        EdgeInsets.all(AppSpacing.space3),
      ),
      shape: const WidgetStatePropertyAll<OutlinedBorder>(CircleBorder()),
      animationDuration: AppMotion.base,
    ),
  );

  static FloatingActionButtonThemeData get floatingAction =>
      const FloatingActionButtonThemeData(
        backgroundColor: GeneratedTokens.actionPrimary,
        foregroundColor: GeneratedTokens.textInverse,
        focusColor: GeneratedTokens.actionPrimaryHover,
        hoverColor: GeneratedTokens.actionPrimaryHover,
        elevation: AppElevation.raised,
        focusElevation: AppElevation.raised,
        hoverElevation: AppElevation.raised,
        highlightElevation: AppElevation.raised,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.mediumAll),
      );

  static SegmentedButtonThemeData get segmentedButton =>
      SegmentedButtonThemeData(
        style: ButtonStyle(
          backgroundColor: WidgetStateProperty.resolveWith<Color>(
            _secondaryBackground,
          ),
          foregroundColor: WidgetStateProperty.resolveWith<Color>(
            _secondaryForeground,
          ),
          side: WidgetStateProperty.resolveWith<BorderSide>(_secondarySide),
          textStyle: WidgetStatePropertyAll<TextStyle>(
            AppTypography.labelLarge,
          ),
          minimumSize: const WidgetStatePropertyAll<Size>(_minimumSize),
          padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(
            _labelPadding,
          ),
          elevation: const WidgetStatePropertyAll<double>(AppElevation.flat),
          animationDuration: AppMotion.base,
        ),
      );

  static ButtonStyle get _primary => ButtonStyle(
    backgroundColor: WidgetStateProperty.resolveWith<Color>(_primaryBackground),
    foregroundColor: WidgetStateProperty.resolveWith<Color>(_primaryForeground),
    elevation: const WidgetStatePropertyAll<double>(AppElevation.flat),
    shadowColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    surfaceTintColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    shape: const WidgetStatePropertyAll<OutlinedBorder>(_pill),
    padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(_labelPadding),
    minimumSize: const WidgetStatePropertyAll<Size>(_minimumSize),
    textStyle: WidgetStatePropertyAll<TextStyle>(AppTypography.labelLarge),
    animationDuration: AppMotion.base,
  );

  static ButtonStyle get _secondary => ButtonStyle(
    backgroundColor: WidgetStateProperty.resolveWith<Color>(
      _secondaryBackground,
    ),
    foregroundColor: WidgetStateProperty.resolveWith<Color>(
      _secondaryForeground,
    ),
    side: WidgetStateProperty.resolveWith<BorderSide>(_secondarySide),
    elevation: const WidgetStatePropertyAll<double>(AppElevation.flat),
    shadowColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    surfaceTintColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    shape: const WidgetStatePropertyAll<OutlinedBorder>(_pill),
    padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(_labelPadding),
    minimumSize: const WidgetStatePropertyAll<Size>(_minimumSize),
    textStyle: WidgetStatePropertyAll<TextStyle>(AppTypography.labelLarge),
    animationDuration: AppMotion.base,
  );

  static ButtonStyle get _quiet => ButtonStyle(
    backgroundColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    foregroundColor: WidgetStateProperty.resolveWith<Color>(
      _secondaryForeground,
    ),
    elevation: const WidgetStatePropertyAll<double>(AppElevation.flat),
    shadowColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    surfaceTintColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    shape: const WidgetStatePropertyAll<OutlinedBorder>(_pill),
    padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(_labelPadding),
    minimumSize: const WidgetStatePropertyAll<Size>(_minimumSize),
    textStyle: WidgetStatePropertyAll<TextStyle>(AppTypography.labelLarge),
    animationDuration: AppMotion.base,
  );

  static Color _primaryBackground(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) {
      return GeneratedTokens.actionPrimaryDisabled;
    }
    if (states.contains(WidgetState.pressed)) {
      return GeneratedTokens.actionPrimaryActive;
    }
    return states.contains(WidgetState.hovered)
        ? GeneratedTokens.actionPrimaryHover
        : GeneratedTokens.actionPrimary;
  }

  static Color _primaryForeground(Set<WidgetState> states) =>
      states.contains(WidgetState.disabled)
      ? GeneratedTokens.textSecondary
      : GeneratedTokens.textInverse;

  static Color _secondaryBackground(Set<WidgetState> states) =>
      states.contains(WidgetState.pressed)
      ? GeneratedTokens.borderSubtle
      : Colors.transparent;

  static Color _secondaryForeground(Set<WidgetState> states) =>
      states.contains(WidgetState.disabled)
      ? GeneratedTokens.textMuted
      : GeneratedTokens.textPrimary;

  static BorderSide _secondarySide(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) {
      return const BorderSide(
        color: GeneratedTokens.borderSubtle,
        width: AppBorders.control,
      );
    }
    if (states.contains(WidgetState.focused)) return AppBorders.focusedSide;
    return AppBorders.controlSide;
  }

  static Color _iconForeground(Set<WidgetState> states) =>
      states.contains(WidgetState.disabled)
      ? GeneratedTokens.textMuted
      : GeneratedTokens.textPrimary;
}
