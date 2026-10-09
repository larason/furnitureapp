import '../../../core/network/api_error.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import '../../catalog/data/fixture_products.dart';
import '../../catalog/data/product_summary.dart';
import 'product_detail.dart';
import 'product_detail_repository.dart';

/// Approved development fixture product details.
///
/// The identity, category, price, and cover image of every entry come from the
/// shared [fixtureProductSummaries], so the catalog grid, the category landing
/// page, and this detail page always open the same product. The description is
/// the same copy `frontend/web/lib/homepage/fixtures.ts` already approves for
/// `HOMEPAGE_PRODUCT_DETAIL_FIXTURES`.
///
/// The second gallery image and the two variants per product are **synthetic
/// development values added by Phase 17.3**. They exist only so the gallery
/// position indicator and variant selection can be exercised locally without a
/// populated Laravel database. They are not inventory, not a quotation, and
/// not a claim about any real product.
const String _roomImageAsset =
    'assets/furnitures/fixtures/categories/living-room.jpg';
const String _roomImageAlt =
    'A living room arranged with seating and a low wooden table.';
const String _fixtureDescription =
    'A made-to-order furniture piece shown as part of the design preview.';
const String _fixtureCategoryDescription = 'Furniture for living spaces.';
const String _fixtureTimestamp = '2026-01-01T00:00:00Z';

final List<ProductDetail> _fixtureDetails = fixtureProductSummaries
    .map(_detailFor)
    .toList(growable: false);

ProductDetail _detailFor(ProductSummary summary) {
  final cover = summary.primaryImage!;
  return ProductDetail(
    id: summary.id,
    slug: summary.slug,
    name: summary.name,
    productType: summary.productType,
    price: summary.price,
    category: ProductDetailCategory(
      summary: summary.category,
      description: _fixtureCategoryDescription,
    ),
    primaryImage: cover,
    availability: summary.availability,
    stockIndicator: summary.stockIndicator,
    description: _fixtureDescription,
    images: <ProductDetailImage>[
      ProductDetailImage(
        id: '${summary.id}_image',
        url: cover.url,
        altText: cover.altText,
        sortOrder: 0,
        isPrimary: true,
        assetPath: cover.assetPath,
      ),
      ProductDetailImage(
        id: '${summary.id}_room_image',
        url: '',
        altText: _roomImageAlt,
        sortOrder: 1,
        isPrimary: false,
        assetPath: _roomImageAsset,
      ),
    ],
    variants: <ProductVariantSummary>[
      _variant(
        summary,
        code: 'STD',
        name: 'Standard',
        amount: summary.price.amount,
      ),
      _variant(
        summary,
        code: 'EXT',
        name: 'Extended',
        amount: _wholeShillings(
          summary.price.amount + summary.price.amount ~/ 7,
        ),
      ),
    ],
    createdAt: DateTime.parse(_fixtureTimestamp),
    updatedAt: DateTime.parse(_fixtureTimestamp),
  );
}

/// Rounds a minor-unit amount up to a whole shilling.
///
/// Furniture prices are whole shillings. Truncating a percentage uplift with
/// integer division alone would leave stray cents in the development catalog
/// and make a clean fixture price look like a rounding error.
int _wholeShillings(int amount) =>
    ((amount + _minorUnitsPerShilling - 1) ~/ _minorUnitsPerShilling) *
    _minorUnitsPerShilling;

const int _minorUnitsPerShilling = 100;

ProductVariantSummary _variant(
  ProductSummary summary, {
  required String code,
  required String name,
  required int amount,
}) => ProductVariantSummary(
  id: 'fixture_${summary.id}_$code',
  sku: '${summary.id.toUpperCase()}-$code',
  name: name,
  price: Money(amount: amount, currency: 'TZS'),
  availability: 'available',
  stockIndicator: 'MADE_TO_ORDER',
);

/// Development-only product detail source.
///
/// It resolves the same slugs and opaque IDs as [fixtureProductSummaries], so a
/// catalog card opens a detail page that agrees with the listing. An unknown
/// identifier produces the same `RESOURCE_NOT_FOUND` (404) the real API returns
/// for a non-public product, so the unavailable state is exercised in fixture
/// mode too.
class FixtureProductDetailRepository implements ProductDetailRepository {
  @override
  Future<ProductDetail> getProduct(
    String product, {
    RequestCancellation? cancellation,
  }) async {
    if (cancellation?.isCancelled ?? false) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }
    for (final detail in _fixtureDetails) {
      if (detail.id == product || detail.slug == product) return detail;
    }
    throw const ApiError(
      statusCode: 404,
      errors: <ApiErrorItem>[
        ApiErrorItem(
          code: 'RESOURCE_NOT_FOUND',
          message: 'The requested product was not found.',
        ),
      ],
    );
  }
}
