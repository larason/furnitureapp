import 'package:flutter/material.dart';

import '../app_shapes.dart';
import '../app_spacing.dart';
import '../app_typography.dart';
import '../tokens/generated_tokens.dart';

/// Navigation themes. The app bar mirrors the website header: the warm canvas,
/// a soft hairline rule, no elevation, and no surface tinting. The bottom
/// navigation bar uses paper so it separates from the canvas without a shadow.
abstract final class AppNavigationThemes {
  static final AppBarThemeData appBar = AppBarThemeData(
    backgroundColor: GeneratedTokens.surfaceCanvas,
    foregroundColor: GeneratedTokens.textPrimary,
    elevation: AppElevation.flat,
    scrolledUnderElevation: AppElevation.flat,
    shadowColor: Colors.transparent,
    surfaceTintColor: Colors.transparent,
    centerTitle: false,
    titleTextStyle: AppTypography.titleLarge.copyWith(
      color: GeneratedTokens.textPrimary,
    ),
    iconTheme: const IconThemeData(color: GeneratedTokens.textPrimary),
    shape: const Border(
      bottom: BorderSide(
        color: GeneratedTokens.borderSubtle,
        width: AppBorders.ring,
      ),
    ),
  );

  static final NavigationBarThemeData navigationBar = NavigationBarThemeData(
    backgroundColor: GeneratedTokens.surfacePaper,
    elevation: AppElevation.flat,
    shadowColor: Colors.transparent,
    surfaceTintColor: Colors.transparent,
    indicatorColor: GeneratedTokens.borderSubtle,
    indicatorShape: const StadiumBorder(),
    labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
    labelTextStyle: WidgetStateProperty.resolveWith<TextStyle>(
      _navigationLabel,
    ),
    iconTheme: WidgetStateProperty.resolveWith<IconThemeData>(_navigationIcon),
  );

  static const TabBarThemeData tabBar = TabBarThemeData(
    labelColor: GeneratedTokens.textPrimary,
    unselectedLabelColor: GeneratedTokens.textSecondary,
    indicatorColor: GeneratedTokens.actionPrimary,
    indicatorSize: TabBarIndicatorSize.tab,
    dividerColor: GeneratedTokens.borderSubtle,
  );

  static final ListTileThemeData listTile = ListTileThemeData(
    contentPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.space4),
    textColor: GeneratedTokens.textPrimary,
    iconColor: GeneratedTokens.textSecondary,
    subtitleTextStyle: AppTypography.bodyMedium.copyWith(
      color: GeneratedTokens.textSecondary,
    ),
  );

  static TextStyle _navigationLabel(Set<WidgetState> states) =>
      states.contains(WidgetState.selected)
      ? AppTypography.labelMedium.copyWith(color: GeneratedTokens.textPrimary)
      : AppTypography.labelMedium.copyWith(
          color: GeneratedTokens.textSecondary,
        );

  static IconThemeData _navigationIcon(Set<WidgetState> states) =>
      states.contains(WidgetState.selected)
      ? const IconThemeData(color: GeneratedTokens.textPrimary)
      : const IconThemeData(color: GeneratedTokens.textSecondary);
}
