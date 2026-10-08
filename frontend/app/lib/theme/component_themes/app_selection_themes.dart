import 'package:flutter/material.dart';

import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Selection and loading themes. Selection is signalled by charcoal, never by
/// the brand brown, and every control keeps a padded Material touch target.
abstract final class AppSelectionThemes {
  static final ChipThemeData chip = ChipThemeData(
    backgroundColor: GeneratedTokens.surfacePaper,
    selectedColor: GeneratedTokens.borderSubtle,
    disabledColor: GeneratedTokens.borderSubtle,
    side: AppBorders.controlSide,
    shape: const StadiumBorder(),
    labelStyle: AppTypography.labelLarge.copyWith(
      color: GeneratedTokens.textPrimary,
    ),
    secondaryLabelStyle: AppTypography.labelLarge.copyWith(
      color: GeneratedTokens.textPrimary,
    ),
    checkmarkColor: GeneratedTokens.actionPrimary,
    deleteIconColor: GeneratedTokens.textSecondary,
    iconTheme: const IconThemeData(color: GeneratedTokens.textSecondary),
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.space3,
      vertical: AppSpacing.space2,
    ),
    labelPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.space2),
    elevation: AppElevation.flat,
    pressElevation: AppElevation.flat,
    shadowColor: Colors.transparent,
    surfaceTintColor: Colors.transparent,
  );

  static final CheckboxThemeData checkbox = CheckboxThemeData(
    fillColor: WidgetStateProperty.resolveWith<Color>(_toggleFill),
    checkColor: const WidgetStatePropertyAll<Color>(
      GeneratedTokens.textInverse,
    ),
    side: AppBorders.controlSide,
    materialTapTargetSize: MaterialTapTargetSize.padded,
  );

  static final RadioThemeData radio = RadioThemeData(
    fillColor: WidgetStateProperty.resolveWith<Color>(_radioFill),
    side: AppBorders.controlSide,
    materialTapTargetSize: MaterialTapTargetSize.padded,
  );

  static final SwitchThemeData switchTheme = SwitchThemeData(
    thumbColor: WidgetStateProperty.resolveWith<Color>(_switchThumb),
    trackColor: WidgetStateProperty.resolveWith<Color>(_switchTrack),
    trackOutlineColor: const WidgetStatePropertyAll<Color>(
      GeneratedTokens.borderDefault,
    ),
    trackOutlineWidth: const WidgetStatePropertyAll<double>(AppBorders.control),
    materialTapTargetSize: MaterialTapTargetSize.padded,
  );

  static const ProgressIndicatorThemeData progressIndicator =
      ProgressIndicatorThemeData(
        color: GeneratedTokens.actionPrimary,
        linearTrackColor: GeneratedTokens.borderSubtle,
        circularTrackColor: GeneratedTokens.borderSubtle,
      );

  static Color _toggleFill(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) {
      return GeneratedTokens.borderSubtle;
    }
    if (states.contains(WidgetState.selected)) {
      return GeneratedTokens.actionPrimary;
    }
    return Colors.transparent;
  }

  static Color _radioFill(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) {
      return GeneratedTokens.borderSubtle;
    }
    if (states.contains(WidgetState.selected)) {
      return GeneratedTokens.actionPrimary;
    }
    return GeneratedTokens.borderDefault;
  }

  static Color _switchThumb(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) {
      return GeneratedTokens.borderSubtle;
    }
    return states.contains(WidgetState.selected)
        ? GeneratedTokens.textInverse
        : GeneratedTokens.borderDefault;
  }

  static Color _switchTrack(Set<WidgetState> states) =>
      states.contains(WidgetState.selected)
      ? GeneratedTokens.actionPrimary
      : GeneratedTokens.borderSubtle;
}
