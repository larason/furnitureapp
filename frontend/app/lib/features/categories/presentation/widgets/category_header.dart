import 'package:flutter/material.dart';

import '../../../../theme/app_media.dart';
import '../../../../theme/app_spacing.dart';
import '../../../catalog/presentation/catalog_image.dart';
import '../../data/category_detail.dart';

/// Editorial header for a category landing page: room photography, the
/// category name as the semantic heading, and the description when the API
/// provides one.
///
/// No text is placed over the photograph, and no caption, badge, or count is
/// shown that the contract does not provide. The image carries the server
/// category name as its alternative text because CAT-004 documents no alt
/// field for category images.
class CategoryHeader extends StatelessWidget {
  const CategoryHeader({super.key, required this.category});

  final CategoryDetail category;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final image = category.image;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        if (image != null) ...<Widget>[
          AspectRatio(
            aspectRatio: AppMedia.editorial,
            child: CatalogImage(
              url: image.url,
              assetPath: image.assetPath,
              semanticLabel: category.name,
            ),
          ),
          const SizedBox(height: AppSpacing.space5),
        ],
        Semantics(
          header: true,
          child: Text(category.name, style: theme.textTheme.headlineLarge),
        ),
        if (category.description case final description?) ...<Widget>[
          const SizedBox(height: AppSpacing.space3),
          Text(description, style: theme.textTheme.bodyLarge),
        ],
      ],
    );
  }
}
