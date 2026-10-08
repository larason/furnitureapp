import 'package:flutter/material.dart';

import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Feedback themes. Transient confirmation uses the inverse surface; tooltips
/// use the same surface so they never introduce an unapproved container colour.
abstract final class AppFeedbackThemes {
  static final SnackBarThemeData snackBar = SnackBarThemeData(
    behavior: SnackBarBehavior.floating,
    backgroundColor: GeneratedTokens.surfaceInverse,
    contentTextStyle: AppTypography.bodyMedium.copyWith(
      color: GeneratedTokens.textInverse,
    ),
    actionTextColor: GeneratedTokens.textInverse,
    elevation: AppElevation.raised,
    shape: const RoundedRectangleBorder(borderRadius: AppRadii.smallAll),
    insetPadding: const EdgeInsets.all(AppSpacing.space4),
    showCloseIcon: true,
    closeIconColor: GeneratedTokens.textInverse,
  );

  static final TooltipThemeData tooltip = TooltipThemeData(
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.space3,
      vertical: AppSpacing.space2,
    ),
    decoration: const BoxDecoration(
      color: GeneratedTokens.surfaceInverse,
      borderRadius: AppRadii.smallAll,
    ),
    textStyle: AppTypography.bodySmall.copyWith(
      color: GeneratedTokens.textInverse,
    ),
  );
}
