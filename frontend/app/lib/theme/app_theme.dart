import 'package:flutter/material.dart';

import 'app_color_extensions.dart';
import 'app_color_scheme.dart';
import 'app_typography.dart';
import 'component_themes/app_button_themes.dart';
import 'component_themes/app_feedback_themes.dart';
import 'component_themes/app_input_themes.dart';
import 'component_themes/app_navigation_themes.dart';
import 'component_themes/app_selection_themes.dart';
import 'component_themes/app_surface_themes.dart';
import 'tokens/generated_tokens.dart';

/// The single Material 3 theme adapter for the SL Furnitures app.
///
/// Only a light theme exists. `--surface-inverse` is an inverse surface, not a
/// dark mode; dark support is deferred until the token contract defines it.
/// Every value originates in `tokens.css` via [GeneratedTokens].
abstract final class AppTheme {
  static const List<ThemeExtension<dynamic>> extensions =
      <ThemeExtension<dynamic>>[
        AppSurfaceColors.standard,
        AppBrandColors.standard,
        AppStatusColors.standard,
      ];

  static ThemeData light() => ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: AppColorScheme.light,
    scaffoldBackgroundColor: GeneratedTokens.surfaceCanvas,
    textTheme: AppTypography.textTheme,
    extensions: extensions,
    appBarTheme: AppNavigationThemes.appBar,
    navigationBarTheme: AppNavigationThemes.navigationBar,
    tabBarTheme: AppNavigationThemes.tabBar,
    listTileTheme: AppNavigationThemes.listTile,
    filledButtonTheme: AppButtonThemes.filled,
    elevatedButtonTheme: AppButtonThemes.elevated,
    outlinedButtonTheme: AppButtonThemes.outlined,
    textButtonTheme: AppButtonThemes.text,
    iconButtonTheme: AppButtonThemes.icon,
    floatingActionButtonTheme: AppButtonThemes.floatingAction,
    segmentedButtonTheme: AppButtonThemes.segmentedButton,
    inputDecorationTheme: AppInputThemes.inputDecoration,
    searchBarTheme: AppInputThemes.searchBar,
    cardTheme: AppSurfaceThemes.card,
    dialogTheme: AppSurfaceThemes.dialog,
    bottomSheetTheme: AppSurfaceThemes.bottomSheet,
    dividerTheme: AppSurfaceThemes.divider,
    chipTheme: AppSelectionThemes.chip,
    checkboxTheme: AppSelectionThemes.checkbox,
    radioTheme: AppSelectionThemes.radio,
    switchTheme: AppSelectionThemes.switchTheme,
    progressIndicatorTheme: AppSelectionThemes.progressIndicator,
    snackBarTheme: AppFeedbackThemes.snackBar,
    tooltipTheme: AppFeedbackThemes.tooltip,
  );
}
