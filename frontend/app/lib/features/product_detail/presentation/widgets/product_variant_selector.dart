import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../../catalog/presentation/product_card.dart';
import '../../data/product_detail.dart';

/// Available options for a product.
///
/// CAT-002 embeds the active variant summaries, so no `CAT-005` request is made
/// and no standalone variant lookup exists. Selection is a single-choice group
/// keyed on the stable variant ID; nothing is selected by default, so the
/// product's own base price stays visible until the customer chooses.
///
/// Only contract fields are shown: option name, SKU, price, and the coarse
/// public availability. No colour, fabric, dimension, material, or finish is
/// derived from the name or the SKU, and no inventory quantity is displayed.
class ProductVariantSelector extends StatelessWidget {
  const ProductVariantSelector({
    super.key,
    required this.variants,
    required this.selectedVariantId,
    required this.onChanged,
  });

  final List<ProductVariantSummary> variants;
  final String? selectedVariantId;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return RadioGroup<String>(
      groupValue: selectedVariantId,
      onChanged: onChanged,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Semantics(
            header: true,
            child: Text('Available options', style: theme.textTheme.titleLarge),
          ),
          const SizedBox(height: AppSpacing.space1),
          Text(
            'Choose an option to see its price.',
            style: theme.textTheme.bodyMedium,
          ),
          const SizedBox(height: AppSpacing.space3),
          for (final variant in variants)
            RadioListTile<String>(
              key: ValueKey<String>('product_detail.variant.${variant.id}'),
              value: variant.id,
              selected: variant.id == selectedVariantId,
              contentPadding: EdgeInsets.zero,
              title: Text(variant.name),
              subtitle: Text(
                '${formatTzs(variant.price)} · ${variant.sku} · '
                '${_availabilityLabel(variant)}',
              ),
            ),
        ],
      ),
    );
  }

  /// Coarse public availability only. `MADE_TO_ORDER` is a first-class request
  /// path, never a shortage.
  static String _availabilityLabel(ProductVariantSummary variant) =>
      switch (variant.availability) {
        'unavailable' => 'Currently unavailable',
        _ => switch (variant.stockIndicator) {
          'MADE_TO_ORDER' => 'Made to order',
          'LOW_STOCK' => 'Limited availability',
          _ => 'In stock',
        },
      };
}
