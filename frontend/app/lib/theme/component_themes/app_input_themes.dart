import 'package:flutter/material.dart';

import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Input themes. Fields are outlined and unfilled, matching the website's
/// outlined MUI inputs, with the canonical control radius and border width.
/// Focus uses the canonical `--focus-ring` colour and width because Material
/// exposes no outer focus ring.
abstract final class AppInputThemes {
  static const OutlineInputBorder _enabledBorder = OutlineInputBorder(
    borderRadius: AppRadii.smallAll,
    borderSide: AppBorders.controlSide,
  );
  static const OutlineInputBorder _focusedBorder = OutlineInputBorder(
    borderRadius: AppRadii.smallAll,
    borderSide: AppBorders.focusedSide,
  );
  static const OutlineInputBorder _errorBorder = OutlineInputBorder(
    borderRadius: AppRadii.smallAll,
    borderSide: BorderSide(
      color: GeneratedTokens.colorDanger,
      width: AppBorders.control,
    ),
  );
  static const OutlineInputBorder _focusedErrorBorder = OutlineInputBorder(
    borderRadius: AppRadii.smallAll,
    borderSide: BorderSide(
      color: GeneratedTokens.colorDanger,
      width: AppBorders.focus,
    ),
  );
  static const OutlineInputBorder _disabledBorder = OutlineInputBorder(
    borderRadius: AppRadii.smallAll,
    borderSide: BorderSide(
      color: GeneratedTokens.borderSubtle,
      width: AppBorders.control,
    ),
  );

  static final InputDecorationThemeData inputDecoration =
      InputDecorationThemeData(
        filled: false,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.space4,
          vertical: AppSpacing.space4,
        ),
        border: _enabledBorder,
        enabledBorder: _enabledBorder,
        focusedBorder: _focusedBorder,
        errorBorder: _errorBorder,
        focusedErrorBorder: _focusedErrorBorder,
        disabledBorder: _disabledBorder,
        labelStyle: AppTypography.bodyMedium.copyWith(
          color: GeneratedTokens.textSecondary,
        ),
        floatingLabelStyle: AppTypography.labelLarge.copyWith(
          color: GeneratedTokens.textPrimary,
        ),
        hintStyle: AppTypography.bodyMedium.copyWith(
          color: GeneratedTokens.textSecondary,
        ),
        helperStyle: AppTypography.bodySmall.copyWith(
          color: GeneratedTokens.textSecondary,
        ),
        errorStyle: AppTypography.bodySmall.copyWith(
          color: GeneratedTokens.colorDanger,
        ),
        prefixIconColor: GeneratedTokens.textSecondary,
        suffixIconColor: GeneratedTokens.textSecondary,
        iconColor: GeneratedTokens.textSecondary,
        helperMaxLines: 2,
        errorMaxLines: 2,
      );

  static final SearchBarThemeData searchBar = SearchBarThemeData(
    backgroundColor: const WidgetStatePropertyAll<Color>(
      GeneratedTokens.surfacePaper,
    ),
    elevation: const WidgetStatePropertyAll<double>(AppElevation.flat),
    shadowColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    surfaceTintColor: const WidgetStatePropertyAll<Color>(Colors.transparent),
    side: const WidgetStatePropertyAll<BorderSide>(AppBorders.controlSide),
    shape: const WidgetStatePropertyAll<OutlinedBorder>(
      RoundedRectangleBorder(borderRadius: AppRadii.smallAll),
    ),
    padding: const WidgetStatePropertyAll<EdgeInsetsGeometry>(
      EdgeInsets.symmetric(
        horizontal: AppSpacing.space4,
        vertical: AppSpacing.space3,
      ),
    ),
    textStyle: WidgetStatePropertyAll<TextStyle>(AppTypography.bodyLarge),
    hintStyle: WidgetStatePropertyAll<TextStyle>(
      AppTypography.bodyLarge.copyWith(color: GeneratedTokens.textSecondary),
    ),
  );
}
