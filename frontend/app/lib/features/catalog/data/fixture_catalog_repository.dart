import '../../../core/network/api_response.dart';
import '../../../core/network/api_transport_exception.dart';
import '../../../core/network/request_cancellation.dart';
import 'catalog_query.dart';
import 'catalog_repository.dart';
import 'fixture_products.dart';
import 'product_summary.dart';

/// Development-only CAT-001 implementation over the approved fixture catalog.
///
/// Search, category filtering, sorting, and pagination follow the frozen
/// contract's parameter semantics. Two behaviours cannot be reproduced exactly
/// without Laravel and are documented here rather than guessed at:
///
/// - Laravel searches product name, description, variant SKU, and variant
///   attribute JSON. A `ProductSummary` carries only name, slug, and category,
///   so this matches name, slug, and category name instead.
/// - `created_at` is not part of the public product summary, so
///   [CatalogSort.newest] keeps the declared fixture order, which is already the
///   order the seeder created the products in.
class FixtureCatalogRepository implements CatalogRepository {
  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    if (cancellation?.isCancelled ?? false) {
      throw const ApiTransportException(
        kind: ApiTransportFailureKind.cancellation,
      );
    }
    return _page(query, _matching(query));
  }

  List<ProductSummary> _matching(CatalogQuery query) {
    final search = query.normalizedSearch?.toLowerCase();
    return fixtureProductSummaries
        .where((product) {
          if (query.categorySlug != null &&
              product.category.slug != query.categorySlug) {
            return false;
          }
          return search == null || _matches(product, search);
        })
        .toList(growable: false);
  }

  bool _matches(ProductSummary product, String search) =>
      product.name.toLowerCase().contains(search) ||
      product.slug.toLowerCase().contains(search) ||
      product.category.name.toLowerCase().contains(search);

  CatalogPage _page(CatalogQuery query, List<ProductSummary> matched) {
    final ordered = _sorted(matched, query.sort);
    final start = (query.page - 1) * query.perPage;
    final items = start < ordered.length
        ? ordered.skip(start).take(query.perPage).toList(growable: false)
        : const <ProductSummary>[];
    final lastPage = ordered.isEmpty
        ? 1
        : ((ordered.length - 1) ~/ query.perPage) + 1;
    final currentPage = query.page > lastPage ? lastPage : query.page;
    return CatalogPage(
      products: items,
      pagination: ApiPagination(
        currentPage: currentPage,
        perPage: query.perPage,
        total: ordered.length,
        lastPage: lastPage,
        hasNext: currentPage < lastPage,
        hasPrevious: currentPage > CatalogQuery.firstPage,
      ),
    );
  }

  List<ProductSummary> _sorted(List<ProductSummary> items, CatalogSort sort) {
    if (sort == CatalogSort.newest) return items;
    final int Function(ProductSummary, ProductSummary) compare = switch (sort) {
      CatalogSort.nameAToZ =>
        (ProductSummary a, ProductSummary b) =>
            a.name.toLowerCase().compareTo(b.name.toLowerCase()),
      CatalogSort.priceLowToHigh =>
        (ProductSummary a, ProductSummary b) =>
            a.price.amount.compareTo(b.price.amount),
      _ => (ProductSummary a, ProductSummary b) => b.price.amount.compareTo(
        a.price.amount,
      ),
    };
    return <ProductSummary>[...items]
      ..sort((ProductSummary a, ProductSummary b) {
        final result = compare(a, b);
        return result != 0 ? result : a.id.compareTo(b.id);
      });
  }
}
