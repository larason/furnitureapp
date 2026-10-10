import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../data/product_detail.dart';

/// The request-first customer action for a made-to-order piece.
///
/// The enabled button calls [onRequest] to open the furniture request flow.
/// It does not create an order, payment, deposit, reservation, or cart action.
///
/// No cart, checkout, payment, deposit, reservation, or "buy now" control
/// appears anywhere on the page.
class ProductRequestAction extends StatelessWidget {
  const ProductRequestAction({
    super.key,
    required this.selectedVariant,
    required this.onRequest,
  });

  final ProductVariantSummary? selectedVariant;
  final VoidCallback onRequest;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final variant = selectedVariant;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Semantics(
          header: true,
          child: Text(
            'Request this furniture',
            style: theme.textTheme.titleLarge,
          ),
        ),
        const SizedBox(height: AppSpacing.space2),
        Text(
          'Made-to-order pieces are requested rather than bought outright. '
          'Tell us the room, the measurements, and the finish you have in mind, '
          'and our team will confirm what is possible and the final price.',
          style: theme.textTheme.bodyMedium,
        ),
        if (variant != null) ...<Widget>[
          const SizedBox(height: AppSpacing.space3),
          Text(
            'Selected option: ${variant.name}',
            style: theme.textTheme.titleSmall,
          ),
        ],
        const SizedBox(height: AppSpacing.space4),
        Semantics(
          container: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              FilledButton(
                onPressed: onRequest,
                child: const Text('Request this furniture'),
              ),
              const SizedBox(height: AppSpacing.space2),
              Text(
                'Tell us your requirements and we will follow up.',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
