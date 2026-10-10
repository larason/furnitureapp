import 'product_summary.dart';

/// Approved development fixture products.
///
/// These mirror `frontend/web/lib/homepage/fixtures.ts`
/// (`HOMEPAGE_PRODUCT_FIXTURES`), so the local catalog, product detail, and the
/// website agree on identity, price, and imagery. Image files are the bundled
/// project fixtures.
///
/// This is development-only verification content. It is not a production
/// catalog and makes no claim about real inventory.
const List<ProductSummary> fixtureProductSummaries = <ProductSummary>[
  ProductSummary(
    id: 'fixture_armchair',
    slug: 'fixture-open-frame-armchair',
    name: 'Lounge chair',
    productType: 'MADE_TO_ORDER',
    price: Money(amount: 10000000, currency: 'TZS'),
    category: ProductCategorySummary(
      id: 'fixture_living',
      slug: 'living-room',
      name: 'Living Room',
    ),
    primaryImage: ProductImageSummary(
      id: 'fixture_armchair_image',
      url: '',
      altText: 'An open wooden armchair with cream seat and back cushions',
      assetPath: '$_assetRoot/lounge-chair.jpg',
    ),
    availability: 'available',
    stockIndicator: 'MADE_TO_ORDER',
  ),
  ProductSummary(
    id: 'fixture_sofa',
    slug: 'fixture-soft-two-seat-sofa',
    name: 'Soft two-seat sofa',
    productType: 'MADE_TO_ORDER',
    price: Money(amount: 24500000, currency: 'TZS'),
    category: ProductCategorySummary(
      id: 'fixture_living',
      slug: 'living-room',
      name: 'Living Room',
    ),
    primaryImage: ProductImageSummary(
      id: 'fixture_sofa_image',
      url: '',
      altText: 'A cream two-seat sofa with broad upholstered arms',
      assetPath: '$_assetRoot/white-sofa.jpg',
    ),
    availability: 'available',
    stockIndicator: 'MADE_TO_ORDER',
  ),
  ProductSummary(
    id: 'fixture_barrelchair',
    slug: 'fixture-barrel-chair',
    name: 'Barrel chair',
    productType: 'MADE_TO_ORDER',
    price: Money(amount: 10000000, currency: 'TZS'),
    category: ProductCategorySummary(
      id: 'fixture_living',
      slug: 'living-room',
      name: 'Living Room',
    ),
    primaryImage: ProductImageSummary(
      id: 'fixture_barrelchair_image',
      url: '',
      altText: 'A cream barrel chair with a curved back',
      assetPath: '$_assetRoot/barrel-armchair.jpg',
    ),
    availability: 'available',
    stockIndicator: 'MADE_TO_ORDER',
  ),
];

const String _assetRoot = 'assets/furnitures/fixtures/products';
