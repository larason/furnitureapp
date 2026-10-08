import 'package:flutter/painting.dart';

import 'tokens/generated_tokens.dart';

/// Canonical radius scale. Media is sharp; controls use the small radius,
/// grouped surfaces the medium/large radius, and only genuine pills go round.
abstract final class AppRadii {
  static const double small = GeneratedTokens.radiusSm;
  static const double medium = GeneratedTokens.radiusMd;
  static const double large = GeneratedTokens.radiusLg;
  static const double pill = GeneratedTokens.radiusPill;

  static const BorderRadius smallAll = BorderRadius.all(Radius.circular(small));
  static const BorderRadius mediumAll = BorderRadius.all(
    Radius.circular(medium),
  );
  static const BorderRadius largeAll = BorderRadius.all(Radius.circular(large));
  static const BorderRadius largeTop = BorderRadius.vertical(
    top: Radius.circular(large),
  );

  static const OutlinedBorder pillShape = StadiumBorder();
}

/// Canonical border widths and the deliberate Flutter mapping of the CSS ring
/// and focus-ring shadows.
///
/// CSS box shadows do not translate into native elevation, so `--elev-ring` and
/// `--focus-ring` are expressed as Flutter borders at their canonical widths.
abstract final class AppBorders {
  /// `--border-width`, used for control and container outlines.
  static const double control = GeneratedTokens.borderWidth;

  /// `--elev-ring` ring width.
  static const double ring = GeneratedTokens.elevRingSpread;

  /// `--focus-ring` width. Material draws no outer focus ring, so the canonical
  /// width and colour are applied to the focused outline instead.
  static const double focus = GeneratedTokens.focusRingSpread;

  static const BorderSide ringSide = BorderSide(
    color: GeneratedTokens.elevRingColor,
    width: ring,
  );
  static const BorderSide controlSide = BorderSide(
    color: GeneratedTokens.borderDefault,
    width: control,
  );
  static const BorderSide focusedSide = BorderSide(
    color: GeneratedTokens.focusRingColor,
    width: focus,
  );
}

/// Elevation semantics. Flat surfaces come first; only true layers are raised.
abstract final class AppElevation {
  static const double flat = 0;

  /// Material elevation approximating `--elev-raised` (`0 8px 24px` at 12%).
  static const double raised = GeneratedTokens.elevRaisedOffsetY;

  /// `--elev-raised` shadow colour.
  static const Color shadow = GeneratedTokens.elevRaisedColor;
}
