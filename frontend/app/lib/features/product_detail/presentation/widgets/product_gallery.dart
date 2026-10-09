import 'package:flutter/material.dart';

import '../../../../theme/app_media.dart';
import '../../../../theme/app_spacing.dart';
import '../../../catalog/presentation/catalog_image.dart';
import '../../data/product_detail.dart';

/// Product photography gallery.
///
/// The backend's `sort_order ASC, id ASC` order is used as received and the
/// gallery opens on the image the contract marks `is_primary`. Navigation is a
/// native horizontal swipe: there is no autoplay, no zoom, and no custom
/// gallery engine, so a missing gallery, an undecodable asset, or a failed
/// network image falls back to the shared neutral placeholder instead of
/// failing the page.
class ProductGallery extends StatefulWidget {
  const ProductGallery({
    super.key,
    required this.images,
    required this.initialIndex,
  });

  final List<ProductDetailImage> images;

  /// Page to show first, resolved from the contract's primary image.
  final int initialIndex;

  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  late PageController _controller = _pageController();
  late int _index = _clamp(widget.initialIndex);

  PageController _pageController() =>
      PageController(initialPage: _clamp(widget.initialIndex));

  /// Keeps the visible page inside the current gallery, so a refreshed product
  /// with fewer photographs can never announce a position that does not exist.
  int _clamp(int value) {
    final total = widget.images.length;
    if (total == 0 || value < 0) return 0;
    return value > total - 1 ? total - 1 : value;
  }

  @override
  void didUpdateWidget(ProductGallery oldWidget) {
    super.didUpdateWidget(oldWidget);
    final target = _clamp(widget.initialIndex);
    if (_index == target && _controller.hasClients) return;
    _index = target;
    if (_controller.hasClients) {
      _controller.jumpToPage(target);
    } else {
      _controller.dispose();
      _controller = _pageController();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final images = widget.images;
    if (images.isEmpty) return const _MissingGallery();
    return Column(
      children: <Widget>[
        AspectRatio(
          aspectRatio: AppMedia.productHero,
          child: PageView.builder(
            controller: _controller,
            itemCount: images.length,
            allowImplicitScrolling: true,
            onPageChanged: (index) => setState(() => _index = index),
            itemBuilder: (context, index) => Semantics(
              image: true,
              label: images[index].altText,
              excludeSemantics: true,
              child: CatalogImage(
                url: images[index].url,
                assetPath: images[index].assetPath,
              ),
            ),
          ),
        ),
        if (images.length > 1)
          _PositionIndicator(index: _index, total: images.length),
      ],
    );
  }
}

/// Visible position indicator for a multi-image gallery.
///
/// One live-region announcement states the position; the dots are excluded from
/// semantics so the position is never read twice.
class _PositionIndicator extends StatelessWidget {
  const _PositionIndicator({required this.index, required this.total});

  final int index;
  final int total;

  @override
  Widget build(BuildContext context) {
    final label = 'Showing image ${index + 1} of $total';
    return Semantics(
      liveRegion: true,
      label: label,
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.only(top: AppSpacing.space3),
        child: Column(
          children: <Widget>[
            Text(label, style: Theme.of(context).textTheme.labelMedium),
            const SizedBox(height: AppSpacing.space2),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                for (var dot = 0; dot < total; dot++)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.space1,
                    ),
                    child: _Dot(selected: dot == index),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.selected});

  final bool selected;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: selected ? colorScheme.onSurface : colorScheme.outline,
      ),
      child: const SizedBox.square(dimension: AppSpacing.space2),
    );
  }
}

/// A product published without photography keeps a stable frame instead of
/// collapsing, so the layout never shifts while the rest of the page renders.
class _MissingGallery extends StatelessWidget {
  const _MissingGallery();

  @override
  Widget build(BuildContext context) => AspectRatio(
    aspectRatio: AppMedia.productHero,
    child: const CatalogImage(
      url: '',
      semanticLabel: 'No product photograph available',
    ),
  );
}
