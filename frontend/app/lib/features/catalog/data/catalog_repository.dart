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
  Future<CatalogPage> fetchProducts({
    required int page,
    RequestCancellation? cancellation,
  });
}

class ApiCatalogRepository implements CatalogRepository {
  ApiCatalogRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<CatalogPage> fetchProducts({
    required int page,
    RequestCancellation? cancellation,
  }) async {
    final response = await _apiClient.get<List<ProductSummary>>(
      '/products',
      queryParameters: <String, Object?>{
        'product_type': 'MADE_TO_ORDER',
        'page': page,
        'per_page': 20,
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
