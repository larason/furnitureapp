import 'package:flutter/material.dart';

import '../../../theme/app_spacing.dart';
import '../data/product_summary.dart';

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
      child: Material(
        color: theme.colorScheme.surface,
        child: InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              AspectRatio(
                aspectRatio: 4 / 3,
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
              Text(formatTzs(product.price), style: theme.textTheme.bodyMedium),
              const SizedBox(height: AppSpacing.space1),
              Text('Made to order', style: theme.textTheme.labelMedium),
            ],
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
    if (image == null) return _fallback(context);
    final semantic = image.altText;
    final widget = image.assetPath == null
        ? Image.network(
            image.url,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => _fallback(context),
            loadingBuilder: (_, child, progress) =>
                progress == null ? child : _loading(context),
          )
        : Image.asset(
            image.assetPath!,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => _fallback(context),
          );
    return Semantics(label: semantic, image: true, child: widget);
  }

  Widget _loading(BuildContext context) => ColoredBox(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: const Center(child: CircularProgressIndicator()),
  );

  Widget _fallback(BuildContext context) => ColoredBox(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: const Center(
      child: Icon(
        Icons.chair_outlined,
        semanticLabel: 'Product image unavailable',
      ),
    ),
  );
}

String formatTzs(Money money) {
  final whole = money.amount ~/ 100;
  final minor = money.amount.abs() % 100;
  final formatted = whole.toString().replaceAllMapped(
    RegExp(r'\B(?=(\d{3})+(?!\d))'),
    (_) => ',',
  );
  return minor == 0
      ? 'TZS $formatted'
      : 'TZS $formatted.${minor.toString().padLeft(2, '0')}';
}
