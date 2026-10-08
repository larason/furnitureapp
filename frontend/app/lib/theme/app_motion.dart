import 'package:flutter/widgets.dart';

import 'tokens/generated_tokens.dart';

/// Canonical motion. Motion is quiet and never required to understand state.
abstract final class AppMotion {
  static const Duration fast = GeneratedTokens.motionFast;
  static const Duration base = GeneratedTokens.motionBase;

  /// `--ease-standard`, preserved as cubic-bezier control points.
  static const Curve standard = Cubic(
    GeneratedTokens.easeStandardX1,
    GeneratedTokens.easeStandardY1,
    GeneratedTokens.easeStandardX2,
    GeneratedTokens.easeStandardY2,
  );

  /// Whether the platform asks for reduced motion.
  static bool prefersReducedMotion(BuildContext context) =>
      MediaQuery.maybeOf(context)?.disableAnimations ?? false;

  /// Collapses a canonical duration when reduced motion is requested.
  static Duration resolve(BuildContext context, Duration duration) =>
      prefersReducedMotion(context) ? Duration.zero : duration;
}
