import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/request_cancellation.dart';
import 'catalog_query.dart';
import 'product_summary.dart';

class CatalogPage {
  const CatalogPage({required this.products, required this.pagination});

  final List<ProductSummary> products;
  final ApiPagination pagination;
}

/// Public product discovery (CAT-001).
///
/// Requests are anonymous: no bearer token is attached and no authenticated
/// helper is used, because the public catalog must stay readable without a
/// session. [CatalogQuery] is the only description of what is requested, so a
/// caller can never assemble an unsupported filter or drop the fixed
/// made-to-order restriction.
abstract interface class CatalogRepository {
  /// Fetches one page of made-to-order products matching [query].
  ///
  /// [CatalogQuery.categorySlug] is the server-returned canonical slug from
  /// CAT-003/CAT-004. When supplied, the request becomes the canonical
  /// category product query `GET /products?category={slug}`; the rejected
  /// nested route `/categories/{category}/products` is never used.
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  });
}

class ApiCatalogRepository implements CatalogRepository {
  ApiCatalogRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<CatalogPage> fetchProducts(
    CatalogQuery query, {
    RequestCancellation? cancellation,
  }) async {
    final response = await _apiClient.get<List<ProductSummary>>(
      '/products',
      queryParameters: query.toQueryParameters(),
      cancellation: cancellation,
      decoder: _decodeProducts,
    );
    if (response == null || response.meta?.pagination == null) {
      throw const FormatException('Catalog response is missing pagination.');
    }
    return CatalogPage(
      products: response.data,
      pagination: response.meta!.pagination!,
    );
  }

  static List<ProductSummary> _decodeProducts(Object? value) {
    if (value is! List) {
      throw const FormatException('Catalog data must be a list.');
    }
    return value.map(ProductSummary.fromJson).toList(growable: false);
  }
}
