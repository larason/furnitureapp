import 'package:flutter/material.dart';

import 'tokens/generated_tokens.dart';

/// Editorial surface. Material has no semantic role for a quiet storytelling
/// surface, so it is exposed here rather than forced into a container role.
@immutable
class AppSurfaceColors extends ThemeExtension<AppSurfaceColors> {
  const AppSurfaceColors({required this.editorial});

  final Color editorial;

  static const AppSurfaceColors standard = AppSurfaceColors(
    editorial: GeneratedTokens.surfaceEditorial,
  );

  @override
  AppSurfaceColors copyWith({Color? editorial}) =>
      AppSurfaceColors(editorial: editorial ?? this.editorial);

  @override
  AppSurfaceColors lerp(ThemeExtension<AppSurfaceColors>? other, double t) {
    if (other is! AppSurfaceColors) return this;
    return AppSurfaceColors(
      editorial: Color.lerp(editorial, other.editorial, t) ?? editorial,
    );
  }
}

/// Controlled brand accent for select editorial emphasis and material detail.
/// Never a universal action, heading, border, or icon colour.
@immutable
class AppBrandColors extends ThemeExtension<AppBrandColors> {
  const AppBrandColors({required this.accent, required this.material});

  final Color accent;
  final Color material;

  static const AppBrandColors standard = AppBrandColors(
    accent: GeneratedTokens.accentBrand,
    material: GeneratedTokens.accentMaterial,
  );

  @override
  AppBrandColors copyWith({Color? accent, Color? material}) => AppBrandColors(
    accent: accent ?? this.accent,
    material: material ?? this.material,
  );

  @override
  AppBrandColors lerp(ThemeExtension<AppBrandColors>? other, double t) {
    if (other is! AppBrandColors) return this;
    return AppBrandColors(
      accent: Color.lerp(accent, other.accent, t) ?? accent,
      material: Color.lerp(material, other.material, t) ?? material,
    );
  }
}

/// Status semantics. Danger lives in `ColorScheme.error`; success, warning, and
/// information are not Material roles and must never masquerade as
/// primary/secondary/tertiary.
@immutable
class AppStatusColors extends ThemeExtension<AppStatusColors> {
  const AppStatusColors({
    required this.success,
    required this.warning,
    required this.info,
  });

  final Color success;
  final Color warning;
  final Color info;

  static const AppStatusColors standard = AppStatusColors(
    success: GeneratedTokens.colorSuccess,
    warning: GeneratedTokens.colorWarning,
    info: GeneratedTokens.colorInfo,
  );

  @override
  AppStatusColors copyWith({Color? success, Color? warning, Color? info}) =>
      AppStatusColors(
        success: success ?? this.success,
        warning: warning ?? this.warning,
        info: info ?? this.info,
      );

  @override
  AppStatusColors lerp(ThemeExtension<AppStatusColors>? other, double t) {
    if (other is! AppStatusColors) return this;
    return AppStatusColors(
      success: Color.lerp(success, other.success, t) ?? success,
      warning: Color.lerp(warning, other.warning, t) ?? warning,
      info: Color.lerp(info, other.info, t) ?? info,
    );
  }
}
