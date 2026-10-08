import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/theme/app_color_extensions.dart';
import 'package:sl_furnitures/theme/app_motion.dart';
import 'package:sl_furnitures/theme/app_shapes.dart';
import 'package:sl_furnitures/theme/app_spacing.dart';
import 'package:sl_furnitures/theme/app_theme.dart';
import 'package:sl_furnitures/theme/app_typography.dart';
import 'package:sl_furnitures/theme/tokens/generated_tokens.dart';

TextStyle? _style(TextTheme theme, int index) => switch (index) {
  0 => theme.displayLarge,
  1 => theme.displayMedium,
  2 => theme.displaySmall,
  3 => theme.headlineLarge,
  4 => theme.headlineMedium,
  5 => theme.headlineSmall,
  6 => theme.titleLarge,
  7 => theme.titleMedium,
  8 => theme.titleSmall,
  9 => theme.bodyLarge,
  10 => theme.bodyMedium,
  11 => theme.bodySmall,
  12 => theme.labelLarge,
  13 => theme.labelMedium,
  _ => theme.labelSmall,
};

void main() {
  final theme = AppTheme.light();

  test('is an explicit light Material 3 theme', () {
    expect(theme.useMaterial3, isTrue);
    expect(theme.brightness, Brightness.light);
    expect(theme.colorScheme.brightness, Brightness.light);
  });

  test(
    'maps surfaces, text, and the charcoal action from canonical tokens',
    () {
      expect(theme.colorScheme.surface, GeneratedTokens.surfaceCanvas);
      expect(
        theme.colorScheme.surfaceContainerLowest,
        GeneratedTokens.surfacePaper,
      );
      expect(
        theme.colorScheme.surfaceContainerHighest,
        GeneratedTokens.surfacePaper,
      );
      expect(theme.colorScheme.onSurface, GeneratedTokens.textPrimary);
      expect(theme.colorScheme.onSurfaceVariant, GeneratedTokens.textSecondary);
      expect(theme.colorScheme.primary, GeneratedTokens.actionPrimary);
      expect(theme.colorScheme.onPrimary, GeneratedTokens.textInverse);
      expect(theme.colorScheme.outline, GeneratedTokens.borderDefault);
      expect(theme.colorScheme.outlineVariant, GeneratedTokens.borderSubtle);
      expect(theme.colorScheme.inverseSurface, GeneratedTokens.surfaceInverse);
      expect(theme.colorScheme.error, GeneratedTokens.colorDanger);
      expect(theme.scaffoldBackgroundColor, GeneratedTokens.surfaceCanvas);
    },
  );

  test('never tints a surface through elevation', () {
    expect(theme.colorScheme.surfaceTint, Colors.transparent);
  });

  test('does not assign brand brown to a Material action role', () {
    expect(theme.colorScheme.primary, isNot(GeneratedTokens.accentBrand));
    expect(theme.colorScheme.secondary, isNot(GeneratedTokens.accentBrand));
    expect(theme.colorScheme.primary, GeneratedTokens.colorNeutral950);
  });

  test('uses Young Serif only for display hierarchy', () {
    for (final index in <int>[0, 1, 2, 3]) {
      expect(
        _style(theme.textTheme, index)!.fontFamily,
        AppTypography.displayFamily,
      );
    }
    for (final index in <int>[4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14]) {
      expect(
        _style(theme.textTheme, index)!.fontFamily,
        AppTypography.utilityFamily,
      );
    }
  });

  test('uses only canonical font sizes', () {
    const canonical = <double>[
      GeneratedTokens.textXs,
      GeneratedTokens.textSm,
      GeneratedTokens.textBase,
      GeneratedTokens.textLg,
      GeneratedTokens.textXl,
      GeneratedTokens.text2xl,
      GeneratedTokens.text3xl,
      GeneratedTokens.text4xl,
    ];
    for (var index = 0; index < 15; index++) {
      expect(canonical, contains(_style(theme.textTheme, index)!.fontSize));
    }
  });

  test('exposes the editorial, accent, and status extensions', () {
    final surfaces = theme.extension<AppSurfaceColors>();
    final brand = theme.extension<AppBrandColors>();
    final status = theme.extension<AppStatusColors>();
    expect(surfaces, isNotNull);
    expect(brand, isNotNull);
    expect(status, isNotNull);
    expect(surfaces!.editorial, GeneratedTokens.surfaceEditorial);
    expect(brand!.accent, GeneratedTokens.accentBrand);
    expect(brand.material, GeneratedTokens.accentMaterial);
    expect(status!.success, GeneratedTokens.colorSuccess);
    expect(status.warning, GeneratedTokens.colorWarning);
    expect(status.info, GeneratedTokens.colorInfo);
  });

  test('keeps content flat and raises only true layers', () {
    expect(theme.cardTheme.elevation, AppElevation.flat);
    expect(theme.dialogTheme.elevation, AppElevation.raised);
    expect(theme.bottomSheetTheme.elevation, AppElevation.flat);
    expect(theme.bottomSheetTheme.modalElevation, AppElevation.raised);
    expect(theme.cardTheme.shadowColor, Colors.transparent);
  });

  test('resolves shapes from the canonical radius scale', () {
    final actionShape = theme.filledButtonTheme.style!.shape!.resolve(
      <WidgetState>{},
    );
    expect(actionShape, isA<StadiumBorder>());
    expect(
      theme.cardTheme.shape,
      const RoundedRectangleBorder(borderRadius: AppRadii.mediumAll),
    );
    expect(theme.inputDecorationTheme.enabledBorder, isA<OutlineInputBorder>());
    expect(
      (theme.inputDecorationTheme.enabledBorder! as OutlineInputBorder)
          .borderSide,
      AppBorders.controlSide,
    );
  });

  test('uses the inverse surface for transient feedback', () {
    expect(theme.snackBarTheme.backgroundColor, GeneratedTokens.surfaceInverse);
    expect(theme.snackBarTheme.behavior, SnackBarBehavior.floating);
  });

  test('applies a hairline header rule like the website header', () {
    expect(theme.appBarTheme.backgroundColor, GeneratedTokens.surfaceCanvas);
    expect(theme.appBarTheme.elevation, AppElevation.flat);
    expect(
      theme.appBarTheme.shape,
      const Border(
        bottom: BorderSide(
          color: GeneratedTokens.borderSubtle,
          width: AppBorders.ring,
        ),
      ),
    );
  });

  test('aliases spacing, layout, and motion tokens without new values', () {
    expect(AppSpacing.space4, GeneratedTokens.space4);
    expect(AppSpacing.space10, GeneratedTokens.space10);
    expect(AppSpacing.gutterPhone, GeneratedTokens.containerGutterPhone);
    expect(AppSpacing.breakpointPhone, GeneratedTokens.breakpointPhone);
    expect(AppMotion.fast, GeneratedTokens.motionFast);
    expect(AppMotion.base, GeneratedTokens.motionBase);
    expect(AppRadii.pill, GeneratedTokens.radiusPill);
    expect(AppBorders.control, GeneratedTokens.borderWidth);
    expect(AppElevation.raised, GeneratedTokens.elevRaisedOffsetY);
  });

  testWidgets('honours reduced motion preferences', (tester) async {
    Duration resolved = Duration.zero;
    Widget probe() => Builder(
      builder: (context) {
        resolved = AppMotion.resolve(context, AppMotion.base);
        return const SizedBox.shrink();
      },
    );

    await tester.pumpWidget(MaterialApp(home: probe()));
    expect(resolved, AppMotion.base);

    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(disableAnimations: true),
          child: probe(),
        ),
      ),
    );
    expect(resolved, Duration.zero);
  });
}
