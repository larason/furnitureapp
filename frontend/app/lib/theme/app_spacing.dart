import 'tokens/generated_tokens.dart';

/// Typed access to the canonical spacing and layout scale.
///
/// These are aliases over [GeneratedTokens]; no value is defined here. Use the
/// spacing scale for brand spacing, and the gutter/section tokens for page
/// rhythm. Runtime safe-area and keyboard insets are platform concerns and may
/// use computed values instead.
abstract final class AppSpacing {
  static const double space1 = GeneratedTokens.space1;
  static const double space2 = GeneratedTokens.space2;
  static const double space3 = GeneratedTokens.space3;
  static const double space4 = GeneratedTokens.space4;
  static const double space5 = GeneratedTokens.space5;
  static const double space6 = GeneratedTokens.space6;
  static const double space7 = GeneratedTokens.space7;
  static const double space8 = GeneratedTokens.space8;
  static const double space9 = GeneratedTokens.space9;
  static const double space10 = GeneratedTokens.space10;

  static const double gutterPhone = GeneratedTokens.containerGutterPhone;
  static const double gutterTablet = GeneratedTokens.containerGutterTablet;
  static const double gutterDesktop = GeneratedTokens.containerGutterDesktop;

  static const double sectionPhone = GeneratedTokens.sectionYPhone;
  static const double sectionTablet = GeneratedTokens.sectionYTablet;
  static const double sectionDesktop = GeneratedTokens.sectionYDesktop;

  static const double containerMax = GeneratedTokens.containerMax;
  static const double contentWidthForm = GeneratedTokens.contentWidthForm;
  static const double contentWidthLead = GeneratedTokens.contentWidthLead;

  /// Web breakpoints are exposed for reference. Native layouts should react to
  /// real viewport constraints rather than assuming web columns.
  static const double breakpointPhone = GeneratedTokens.breakpointPhone;
  static const double breakpointTablet = GeneratedTokens.breakpointTablet;
  static const double breakpointDesktop = GeneratedTokens.breakpointDesktop;
}
