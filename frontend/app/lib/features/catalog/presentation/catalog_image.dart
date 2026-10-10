import 'package:flutter/material.dart';

/// Shared catalog media widget.
///
/// One implementation serves product and category photography so loading
/// feedback, fallback behaviour, and semantics cannot drift between features.
/// Network URLs come from the API; [assetPath] is used only by approved
/// development fixtures, which carry a bundled asset instead of a remote URL.
class CatalogImage extends StatelessWidget {
  const CatalogImage({
    super.key,
    required this.url,
    this.assetPath,
    this.semanticLabel,
    this.fallbackIcon = Icons.chair_outlined,
    this.fit = BoxFit.cover,
  });

  final String url;
  final String? assetPath;

  /// Accessible alternative text. When null the media is decorative and is
  /// excluded from semantics, so a parent control keeps a single label.
  final String? semanticLabel;

  final IconData fallbackIcon;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final label = semanticLabel;
    final media = _media(context);
    if (label == null) return ExcludeSemantics(child: media);
    return Semantics(image: true, label: label, child: media);
  }

  /// Missing media is presented as the neutral fallback rather than as a
  /// request. The API documents a nullable image; an empty URL is equally
  /// unusable and must never be fetched.
  Widget _media(BuildContext context) {
    final asset = assetPath;
    if (asset != null) {
      return Image.asset(
        asset,
        fit: fit,
        errorBuilder: (_, _, _) => _fallback(context),
      );
    }
    if (url.isEmpty) return _fallback(context);
    return Image.network(
      url,
      fit: fit,
      errorBuilder: (_, _, _) => _fallback(context),
      loadingBuilder: (_, child, progress) =>
          progress == null ? child : _loading(context),
    );
  }

  Widget _loading(BuildContext context) => ColoredBox(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: const Center(child: CircularProgressIndicator()),
  );

  Widget _fallback(BuildContext context) => ColoredBox(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: Center(child: Icon(fallbackIcon)),
  );
}
