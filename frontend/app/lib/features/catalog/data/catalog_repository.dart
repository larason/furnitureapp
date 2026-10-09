import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/request_cancellation.dart';
import 'product_summary.dart';

class CatalogPage {
  const CatalogPage({required this.products, required this.pagination});

  final List<ProductSummary> products;
  final ApiPagination pagination;
}

abstract interface class CatalogRepository {
  /// Fetches one page of made-to-order products.
  ///
  /// [categorySlug] is the server-returned canonical slug from CAT-003/CAT-004.
  /// When supplied, the request becomes the canonical category product query
  /// `GET /products?category={slug}`; the rejected nested route
  /// `/categories/{category}/products` is never used.
  Future<CatalogPage> fetchProducts({
    required int page,
    String? categorySlug,
    RequestCancellation? cancellation,
  });
}

class ApiCatalogRepository implements CatalogRepository {
  ApiCatalogRepository(this._apiClient);

  static const String _productType = 'MADE_TO_ORDER';
  static const int _perPage = 20;

  final ApiClient _apiClient;

  @override
  Future<CatalogPage> fetchProducts({
    required int page,
    String? categorySlug,
    RequestCancellation? cancellation,
  }) async {
    final response = await _apiClient.get<List<ProductSummary>>(
      '/products',
      queryParameters: <String, Object?>{
        'category': ?categorySlug,
        'product_type': _productType,
        'page': page,
        'per_page': _perPage,
      },
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
