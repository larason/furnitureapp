import '../../../core/network/api_response.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import 'catalog_repository.dart';
import 'fixture_products.dart';
import 'product_summary.dart';

class FixtureCatalogRepository implements CatalogRepository {
  @override
  Future<CatalogPage> fetchProducts({
    required int page,
    String? categorySlug,
    RequestCancellation? cancellation,
  }) async {
    final visible = categorySlug == null
        ? fixtureProductSummaries
        : fixtureProductSummaries
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
