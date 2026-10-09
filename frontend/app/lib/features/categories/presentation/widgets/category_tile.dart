import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../../catalog/presentation/catalog_image.dart';
import '../../data/category_summary.dart';

/// One room story on the categories index.
///
/// The tile is a single accessible control: one label, one large tap target,
/// and a photograph with text below it rather than over it, so contrast never
/// depends on the image. The image itself is decorative because the card
/// already carries one accessible label.
class CategoryTile extends StatelessWidget {
  const CategoryTile({super.key, required this.category, required this.onTap});

  final CategorySummary category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      label: '${category.name}, browse furniture by category',
      onTap: onTap,
      child: ExcludeSemantics(
        child: Material(
          color: theme.colorScheme.surface,
          child: InkWell(
            onTap: onTap,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(child: _image(context)),
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.space3),
                  child: Text(
                    category.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _image(BuildContext context) {
    final image = category.image;
    if (image == null) {
      return ColoredBox(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
      );
    }
    return CatalogImage(url: image.url, assetPath: image.assetPath);
  }
}
