import 'package:flutter/material.dart';

import '../app_color_scheme.dart';
import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Surface themes. Content is flat; only true layers (dialogs, bottom sheets,
/// menus) are raised. Content surfaces stay untinted so photography and the
/// warm canvas carry the colour.
abstract final class AppSurfaceThemes {
  static const CardThemeData card = CardThemeData(
    color: GeneratedTokens.surfacePaper,
    elevation: AppElevation.flat,
    shadowColor: Colors.transparent,
    surfaceTintColor: Colors.transparent,
    margin: EdgeInsets.zero,
    clipBehavior: Clip.antiAlias,
    shape: RoundedRectangleBorder(borderRadius: AppRadii.mediumAll),
  );

  static final DialogThemeData dialog = DialogThemeData(
    backgroundColor: GeneratedTokens.surfacePaper,
    elevation: AppElevation.raised,
    shadowColor: AppElevation.shadow,
    surfaceTintColor: Colors.transparent,
    barrierColor: AppColorScheme.scrim,
    insetPadding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.space6,
      vertical: AppSpacing.space6,
    ),
    titleTextStyle: AppTypography.titleLarge.copyWith(
      color: GeneratedTokens.textPrimary,
    ),
    contentTextStyle: AppTypography.bodyMedium.copyWith(
      color: GeneratedTokens.textSecondary,
    ),
    shape: const RoundedRectangleBorder(borderRadius: AppRadii.largeAll),
  );

  static final BottomSheetThemeData bottomSheet = BottomSheetThemeData(
    backgroundColor: GeneratedTokens.surfacePaper,
    modalBackgroundColor: GeneratedTokens.surfacePaper,
    modalBarrierColor: AppColorScheme.scrim,
    elevation: AppElevation.flat,
    modalElevation: AppElevation.raised,
    shadowColor: AppElevation.shadow,
    surfaceTintColor: Colors.transparent,
    showDragHandle: true,
    dragHandleColor: GeneratedTokens.borderDefault,
    clipBehavior: Clip.antiAlias,
    shape: const RoundedRectangleBorder(borderRadius: AppRadii.largeTop),
  );

  static const DividerThemeData divider = DividerThemeData(
    color: GeneratedTokens.borderSubtle,
    thickness: AppBorders.ring,
    space: AppSpacing.space4,
  );
}
