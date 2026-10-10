import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../../catalog/data/product_summary.dart';
import '../../data/product_detail.dart';
import '../product_labels.dart';
import 'product_variant_selector.dart';

/// Product information: category context, name, price, availability, and the
/// description exactly as the API returned it.
///
/// The composition is deliberately restrained. Text never sits over the
/// photograph, there is no badge stack, no promotional banner, and no
/// specification table the contract does not provide.
class ProductInformation extends StatelessWidget {
  const ProductInformation({
    super.key,
    required this.product,
    required this.displayedPrice,
    required this.isVariantPrice,
    required this.selectedVariantId,
    required this.onVariantChanged,
    required this.onCategoryTap,
  });

  final ProductDetail product;
  final Money? displayedPrice;
  final bool isVariantPrice;
  final String? selectedVariantId;
  final ValueChanged<String?> onVariantChanged;
  final VoidCallback onCategoryTap;

  bool get _madeToOrder => product.productType == 'MADE_TO_ORDER';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final price = displayedPrice;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: TextButton(
            onPressed: onCategoryTap,
            child: Text(
              product.category.summary.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.space3),
        Semantics(
          header: true,
          child: Text(product.name, style: theme.textTheme.headlineLarge),
        ),
        const SizedBox(height: AppSpacing.space3),
        Text(
          productStatusLabel(
            availability: product.availability,
            stockIndicator: product.stockIndicator,
          ),
          style: theme.textTheme.labelLarge,
        ),
        if (price != null) ...<Widget>[
          const SizedBox(height: AppSpacing.space3),
          Text(
            productPriceLabel(
              price,
              madeToOrder: _madeToOrder,
              isVariantPrice: isVariantPrice,
            ),
            style: theme.textTheme.headlineMedium,
          ),
          const SizedBox(height: AppSpacing.space1),
          Text(
            productPriceNote(
              madeToOrder: _madeToOrder,
              isVariantPrice: isVariantPrice,
            ),
            style: theme.textTheme.bodySmall,
          ),
        ],
        if (product.description case final description?) ...<Widget>[
          const SizedBox(height: AppSpacing.space6),
          Semantics(
            header: true,
            child: Text('Description', style: theme.textTheme.titleLarge),
          ),
          const SizedBox(height: AppSpacing.space2),
          Text(description, style: theme.textTheme.bodyLarge),
        ],
        if (product.variants.isNotEmpty) ...<Widget>[
          const SizedBox(height: AppSpacing.space6),
          ProductVariantSelector(
            variants: product.variants,
            selectedVariantId: selectedVariantId,
            onChanged: onVariantChanged,
          ),
        ],
      ],
    );
  }
}
