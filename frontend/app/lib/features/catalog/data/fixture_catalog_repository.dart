import '../../../core/network/api_response.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import 'catalog_repository.dart';
import 'product_summary.dart';

class FixtureCatalogRepository implements CatalogRepository {
  static const _assetRoot = 'assets/furnitures/fixtures/products';

  static const _products = <ProductSummary>[
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

  @override
  Future<CatalogPage> fetchProducts({
    required int page,
    String? categorySlug,
    RequestCancellation? cancellation,
  }) async {
    final visible = categorySlug == null
        ? _products
        : _products
              .where((product) => product.category.slug == categorySlug)
              .toList(growable: false);
    if (cancellation?.isCancelled ?? false) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }
    return _page(page, visible);
  }

  CatalogPage _page(int page, List<ProductSummary> visible) {
    const perPage = 20;
    final start = (page - 1) * perPage;
    final items = start < visible.length
        ? visible.skip(start).take(perPage).toList(growable: false)
        : const <ProductSummary>[];
    return CatalogPage(
      products: items,
      pagination: ApiPagination(
        currentPage: page,
        perPage: perPage,
        total: visible.length,
        lastPage: 1,
        hasNext: false,
        hasPrevious: page > 1,
      ),
    );
  }
}
