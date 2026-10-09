import 'tokens/generated_tokens.dart';

/// Typed access to the canonical media aspect ratios.
///
/// Values are aliases over [GeneratedTokens]; no ratio is defined here. Media
/// keeps its aspect ratio so photography is never distorted by a layout.
abstract final class AppMedia {
  /// Product card and product listing media.
  static const double productCard = GeneratedTokens.mediaProductCard;

  /// Product detail hero media.
  static const double productHero = GeneratedTokens.mediaProductHero;

  /// Editorial and room-context photography.
  static const double editorial = GeneratedTokens.mediaEditorial;
}
