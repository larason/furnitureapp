import '../../catalog/data/product_summary.dart';
import '../../catalog/presentation/product_card.dart';

/// Coarse public availability wording.
///
/// CAT-002 exposes only `availability` and the `stock_indicator` display
/// bucket. No quantity is ever rendered, and `MADE_TO_ORDER` is a first-class
/// request path rather than a shortage, so it is never described as low
/// availability or as out of stock.
String productStatusLabel({
  required String availability,
  required String stockIndicator,
}) => switch (availability) {
  'unavailable' => 'Currently unavailable',
  _ => switch (stockIndicator) {
    'MADE_TO_ORDER' => 'Made to order',
    'LOW_STOCK' => 'Limited availability',
    _ => 'In stock',
  },
};

/// Price wording for a product page.
///
/// Prices are always integer minor units rendered by the Phase 17.1 formatter;
/// no floating-point arithmetic is involved. A made-to-order product shows its
/// price as a starting figure, and a chosen option shows that option's own
/// price. The value is never presented as a confirmed quotation.
String productPriceLabel(
  Money price, {
  required bool madeToOrder,
  required bool isVariantPrice,
}) {
  if (!madeToOrder || isVariantPrice) return formatTzs(price);
  return 'From ${formatTzs(price)}';
}

/// Price explanation shown under the figure.
///
/// A made-to-order figure is indicative: final requirements and pricing come
/// out of the request conversation. A chosen option states its own price, so it
/// is not described as a starting price. Nothing else is implied.
String productPriceNote({
  required bool madeToOrder,
  required bool isVariantPrice,
}) {
  if (!madeToOrder) return 'Price shown in Tanzanian shillings.';
  return isVariantPrice
      ? 'Price for the selected option. Your final price is confirmed when you '
            'request this piece.'
      : 'Indicative starting price. Your final requirements and price are '
            'confirmed when you request this piece.';
}
