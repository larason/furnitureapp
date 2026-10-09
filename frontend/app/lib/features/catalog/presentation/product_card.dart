import 'package:flutter/material.dart';

import '../../../theme/app_media.dart';
import '../../../theme/app_spacing.dart';
import '../data/product_summary.dart';
import 'catalog_image.dart';

class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product, required this.onTap});

  final ProductSummary product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      label: '${product.name}, made to order, ${formatTzs(product.price)}',
      onTap: onTap,
      child: ExcludeSemantics(
        child: Material(
          color: theme.colorScheme.surface,
          child: InkWell(
            onTap: onTap,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                AspectRatio(
                  aspectRatio: AppMedia.productCard,
                  child: ProductImage(product: product),
                ),
                const SizedBox(height: AppSpacing.space3),
                Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: AppSpacing.space1),
                Text(
                  formatTzs(product.price),
                  style: theme.textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpacing.space1),
                Text('Made to order', style: theme.textTheme.labelMedium),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class ProductImage extends StatelessWidget {
  const ProductImage({super.key, required this.product});

  final ProductSummary product;

  @override
  Widget build(BuildContext context) {
    final image = product.primaryImage;
    if (image == null) {
      return const CatalogImage(
        url: '',
        semanticLabel: 'Product image unavailable',
      );
    }
    return CatalogImage(
      url: image.url,
      assetPath: image.assetPath,
      semanticLabel: image.altText,
    );
  }
}

String formatTzs(Money money) {
  final absolute = money.amount.abs();
  final whole = absolute ~/ 100;
  final minor = absolute % 100;
  final formatted = whole.toString().replaceAllMapped(
    RegExp(r'\B(?=(\d{3})+(?!\d))'),
    (_) => ',',
  );
  final sign = money.amount < 0 ? '-' : '';
  return minor == 0
      ? 'TZS $sign$formatted'
      : 'TZS $sign$formatted.${minor.toString().padLeft(2, '0')}';
}
